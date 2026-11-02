<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\LuckyWheel;

use Arma\ArmaPanel\Core\SettingsManager;
use Throwable;
use Arma\ArmaPanel\Modules\Notifications\NotificationService;
use WC_Order;

/**
 * Lucky Wheel Service managing spin tokens, weighted probability, and prizes.
 */
class LuckyWheelService {

    private SettingsManager $settings;
    private ?NotificationService $notificationService;

    /** Spin locks older than this are considered crashed and get broken. */
    private const SPIN_LOCK_TTL = 60;

    public function __construct(SettingsManager $settings, ?NotificationService $notificationService = null) {
        $this->settings            = $settings;
        $this->notificationService = $notificationService;
    }

    /**
     * Check if Lucky Wheel is enabled.
     */
    public function isEnabled(): bool {
        return (bool) $this->settings->get('modules.lucky_wheel', true)
            && (bool) $this->settings->get('lucky_wheel.enabled', true);
    }

    /**
     * Read a plugin setting (exposed for the controller).
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null) {
        return $this->settings->get($key, $default);
    }

    /**
     * Whether an order counts as PAID for wheel purposes.
     *
     * pending / on-hold orders are awaiting payment and must never
     * grant a spin — only really paid orders do.
     */
    public function isOrderPaid(WC_Order $order): bool {
        if (method_exists($order, 'is_paid') && $order->is_paid()) {
            return true;
        }

        if (function_exists('wc_get_is_paid_statuses') && method_exists($order, 'has_status')) {
            return (bool) $order->has_status(wc_get_is_paid_statuses());
        }

        // Last-resort static list (mirrors wc_get_is_paid_statuses()).
        return method_exists($order, 'has_status')
            && (bool) $order->has_status(['processing', 'completed']);
    }

    /**
     * Generate a secure single-use spin token for an order.
     *
     * REUSES the existing token while it is unspent: regenerating on every
     * thank-you-page visit would silently invalidate a wheel tab the customer
     * already has open (token mismatch = "cannot spin" for a spin they never
     * used). A fresh token is only issued when none exists yet.
     *
     * order meta is written with the WooCommerce order CRUD API
     * (update_meta_data/save) — HPOS orders are not posts, so post-meta
     * functions must not be used for writes.
     *
     * @param int $orderId
     * @param int $userId
     * @return string
     */
    public function generateSpinToken(int $orderId, int $userId): string {
        /*
         * A spent spin can never be re-armed: minting a fresh token here
         * would reset the spun flag to '0' and reopen the wheel for an
         * order that already consumed its chance. The order-received
         * redirect checks this too — but any FUTURE caller of
         * getWheelUrl (e.g. a "spin" button in the orders list) relies
         * on this guard alone.
         */
        if ($this->readOrderMeta($orderId, '_arma_wheel_spun') === '1') {
            return '';
        }

        $existing = $this->readOrderMeta($orderId, '_arma_wheel_token');
        if ($existing !== '') {
            return $existing;
        }

        $token = function_exists('wp_generate_password') ? wp_generate_password(32, false) : bin2hex(random_bytes(16));
        $this->writeOrderMeta($orderId, '_arma_wheel_token', $token);
        $this->writeOrderMeta($orderId, '_arma_wheel_spun', '0');
        $this->writeOrderMeta($orderId, '_arma_wheel_user', (string) $userId);

        return $token;
    }

    /**
     * Validate if customer can spin for this order.
     *
     * a spin requires
     *   - a logged-in user ($userId > 0) — guests can never spin;
     *   - a PAID order (pending/on-hold do not count);
     *   - STRICT ownership: the order's customer must be exactly this user.
     *
     * @param int $orderId
     * @param int $userId
     * @param string $token
     * @return bool
     */
    public function canSpin(int $orderId, int $userId, string $token): bool {
        if (!$this->isEnabled() || $orderId <= 0 || empty($token)) {
            return false;
        }

        // Guests can never spin.
        if ($userId <= 0) {
            return false;
        }

        $order = $this->getOrder($orderId);
        if (!$order instanceof WC_Order) {
            return false;
        }

        // Unpaid orders never grant a spin.
        if (!$this->isOrderPaid($order)) {
            return false;
        }

        // Strict ownership: the order must belong to THIS user.
        if ((int) $order->get_customer_id() !== $userId) {
            return false;
        }

        $storedToken = $this->readOrderMeta($orderId, '_arma_wheel_token');
        $isSpun      = $this->readOrderMeta($orderId, '_arma_wheel_spun');
        $orderUser   = (int) $this->readOrderMeta($orderId, '_arma_wheel_user');

        if (empty($storedToken) || strlen($token) !== strlen($storedToken) || !hash_equals($storedToken, $token)) {
            return false;
        }

        if ($isSpun === '1') {
            return false;
        }

        // Ownership check against the user recorded at token generation time
        if ($orderUser > 0 && $orderUser !== $userId) {
            return false;
        }

        return true;
    }

    /**
     * Perform the spin calculation with weighted probability.
     *
     * the whole spin is wrapped in an ATOMIC per-order lock
     * (add_option fails when the option exists), the spent-state is re-read
     * AFTER the lock is acquired, and the lock is always released in finally.
     *
     * @param int $orderId
     * @param int $userId
     * @param string $token
     * @return array{success: bool, slice_index: int, slice: array, message: string}
     */
    public function spin(int $orderId, int $userId, string $token): array {
        if (!$this->canSpin($orderId, $userId, $token)) {
            return [
                'success'     => false,
                'slice_index' => 0,
                'slice'       => [],
                'message'     => __('شما اجازه چرخاندن گردونه برای این سفارش را ندارید یا قبلاً از شانس خود استفاده کرده‌اید.', 'arma-panel'),
            ];
        }

        $slices = (array) $this->settings->get('lucky_wheel.slices', []);
        if (empty($slices)) {
            return [
                'success'     => false,
                'slice_index' => 0,
                'slice'       => [],
                'message'     => __('گردونه شانس در حال حاضر گزینه‌ای ندارد.', 'arma-panel'),
            ];
        }

        // Atomic per-order lock: a second concurrent request for the same
        // order must NEVER manage to spend the spin twice.
        if (!$this->acquireSpinLock($orderId)) {
            return [
                'success'     => false,
                'slice_index' => 0,
                'slice'       => [],
                'message'     => __('چرخش دیگری برای این سفارش در حال پردازش است؛ لطفاً چند لحظه دیگر تلاش کنید.', 'arma-panel'),
            ];
        }

        try {
            /*
             * Re-check under the lock: a concurrent winner may have spent the
             * spin between our first canSpin() and the lock acquisition.
             */
            if (!$this->canSpin($orderId, $userId, $token)) {
                return [
                    'success'     => false,
                    'slice_index' => 0,
                    'slice'       => [],
                    'message'     => __('قبلاً از شانس خود برای این سفارش استفاده کرده‌اید.', 'arma-panel'),
                ];
            }

            /*
             * SPEND the spin FIRST: the "already spun" flag is written BEFORE
             * any coupon is issued. If coupon issuance then fails (or the
             * process dies mid-flight), the order stays spent — a retry can
             * never produce a SECOND coupon for the same order.
             */
            $this->writeOrderMeta($orderId, '_arma_wheel_spun', '1');

            // Weighted random selection
            $selectedIndex = $this->pickWeightedSlice($slices);

            /*
             * When the roll fell into the unassigned remainder and the admin
             * configured NO empty slice at all, the result is simply
             * "no prize" — never a fallback to some prize slice. The spin is
             * spent, nothing is issued.
             */
            if ($selectedIndex < 0) {
                $this->writeOrderMeta($orderId, '_arma_wheel_prize', wp_json_encode(['type' => 'empty']));

                return [
                    'success'     => true,
                    // purely visual landing spot for the wheel animation
                    'slice_index' => max(0, count($slices) - 1),
                    'slice'       => ['type' => 'empty', 'label' => '', 'code' => ''],
                    'message'     => __('متاسفانه در این دور برنده نشدید. در سفارش‌های بعدی منتظر شما هستیم!', 'arma-panel'),
                ];
            }

            $wonSlice = $slices[$selectedIndex];

            /*
             * v1.11.0 prize types: 'coupon' (تخفیف) | 'empty' (پوچ) |
             * 'free_shipping' (ارسال رایگان). Legacy slices saved before the
             * type existed (coupon + free_shipping flag + zero amount) are
             * normalized to a pure free-shipping prize.
             */
            if (($wonSlice['type'] ?? '') === 'coupon'
                && !empty($wonSlice['free_shipping'])
                && (float) ($wonSlice['amount'] ?? 0) <= 0
            ) {
                $wonSlice['type'] = 'free_shipping';
            }

            // Misconfigured-prize guard: a "coupon" slice with no discount amount
            // would award a useless 0% code — treat it as an empty slice so the
            // customer is never handed a dead coupon.
            if (($wonSlice['type'] ?? '') === 'coupon' && (float) ($wonSlice['amount'] ?? 0) <= 0) {
                $wonSlice['type'] = 'empty';
            }

            /*
             * Winning prizes (discount AND free shipping) ALWAYS generate a unique
             * single-use WooCommerce coupon for this winner — there is no shared/
             * static code anymore. The free-shipping prize issues a 0-amount
             * coupon whose only effect is free shipping on the next order.
             */
            if (($wonSlice['type'] ?? '') === 'coupon' || ($wonSlice['type'] ?? '') === 'free_shipping') {
                $isFreeShippingPrize = (($wonSlice['type'] ?? '') === 'free_shipping');
                $personalCode = \Arma\ArmaPanel\Modules\WooCommerce\CouponService::generate(
                    (string) $this->settings->get('lucky_wheel.coupon_prefix', 'ARMA'),
                    $isFreeShippingPrize ? 0.0 : (float) ($wonSlice['amount'] ?? 0),
                    $isFreeShippingPrize ? 'percent' : (string) ($wonSlice['discount_type'] ?? 'percent'),
                    (int) $this->settings->get('lucky_wheel.coupon_expiry_days', 90),
                    $userId,
                    sprintf('جایزه گردونه شانس (%s) — سفارش #%d', $isFreeShippingPrize ? 'ارسال رایگان' : 'تخفیف', $orderId),
                    $isFreeShippingPrize
                );

                if ($personalCode !== '') {
                    $wonSlice['code'] = $personalCode;
                    $this->writeOrderMeta($orderId, '_arma_wheel_coupon', $personalCode);
                } else {
                    /*
                     * Could not issue a personal coupon: never expose any shared
                     * code. The spin stays SPENT (flag already written above)
                     * and an empty prize is recorded — a retry can never mint
                     * a second coupon for this order.
                     */
                    $this->writeOrderMeta($orderId, '_arma_wheel_prize', wp_json_encode(['type' => 'empty']));

                    return [
                        'success'     => true,
                        'slice_index' => $selectedIndex,
                        'slice'       => ['type' => 'empty', 'label' => $wonSlice['label'] ?? '', 'code' => ''],
                        'message'     => __('امتیاز شما ثبت شد اما صدور کد تخفیف با خطا مواجه شد؛ لطفاً با پشتیبانی تماس بگیرید.', 'arma-panel'),
                    ];
                }
            }

            $this->writeOrderMeta($orderId, '_arma_wheel_prize', wp_json_encode($wonSlice));

            // If the prize is a winning coupon (discount or free shipping) and the
            // notification service is available, notify the user
            if (in_array($wonSlice['type'] ?? '', ['coupon', 'free_shipping'], true) && !empty($wonSlice['code']) && $userId > 0 && $this->notificationService) {
                $this->notificationService->create(
                    $userId,
                    __('جایزه گردونه شانس شما!', 'arma-panel'),
                    sprintf(
                        /* translators: 1: prize name, 2: coupon code */
                        __('تبریک! شما در گردونه شانس برنده «%1$s» شدید. کد تخفیف اختصاصی شما: %2$s', 'arma-panel'),
                        esc_html($wonSlice['label']),
                        '<strong>' . esc_html($wonSlice['code']) . '</strong>'
                    ),
                    'system'
                );
            }

            return [
                'success'     => true,
                'slice_index' => $selectedIndex,
                'slice'       => $wonSlice,
                'message'     => in_array($wonSlice['type'] ?? '', ['coupon', 'free_shipping'], true)
                    ? sprintf(__('تبریک! شما برنده شدید: %s', 'arma-panel'), $wonSlice['label'])
                    : __('متاسفانه در این دور برنده نشدید. در سفارش‌های بعدی منتظر شما هستیم!', 'arma-panel'),
            ];
        } finally {
            $this->releaseSpinLock($orderId);
        }
    }

    /**
     * Read the "already spun" flag for an order (HPOS-safe order-meta read
     * with a legacy postmeta fallback). Exposed for the panel controller.
     */
    public function readSpunFlag(int $orderId): string {
        return $this->readOrderMeta($orderId, '_arma_wheel_spun');
    }

    /**
     * Pick a slice index — PERCENT-OF-100 model (v1.5.0).
     *
     *  - Each slice's chance is a percent (0..100).
     *  - chance = 0 means "displayed on the wheel but can NEVER be won"
     *    (e.g. a showcase prize like اسپیکر).
     *  - Configured chances must sum to AT MOST 100 (admin-validated).
     *  - The unassigned remainder (100 - sum) ALWAYS lands on a real EMPTY
     *    ("پوچ") slice — nothing else. coupon / free_shipping
     *    slices (including the legacy free-shipping coupon, which IS a prize)
     *    never absorb the remainder, and when no empty slice exists the
     *    method returns -1 ("no prize", nothing issued).
     *
     * @param array<array<string, mixed>> $slices
     * @return int slice index, or -1 for "remainder with no empty slice"
     */
    private function pickWeightedSlice(array $slices): int {
        $rand = random_int(1, 100);

        $runningSum = 0;
        foreach ($slices as $index => $slice) {
            $chance = max(0, (int) ($slice['chance'] ?? 0));
            if ($chance <= 0) {
                continue; // zero-percent slices never win
            }
            $runningSum += $chance;
            if ($rand <= $runningSum) {
                return (int) $index;
            }
        }

        // $rand fell into the unassigned remainder -> ONLY a genuine empty
        // slice may win it. Prize slices (coupon / free_shipping / legacy
        // free-shipping coupon) are never chosen here, so a zero-percent
        // showcase prize stays unwinnable.
        $emptyIndexes = [];
        foreach ($slices as $index => $slice) {
            if (($slice['type'] ?? '') === 'empty') {
                $emptyIndexes[] = (int) $index;
            }
        }
        if ($emptyIndexes !== []) {
            return $emptyIndexes[array_rand($emptyIndexes)];
        }

        // No empty slice configured at all: "no prize" — the caller must NOT
        // issue anything.
        return -1;
    }

    /**
     * Get redirect URL to Lucky Wheel for an order.
     *
     * @param int $orderId
     * @param int $userId
     * @return string
     */
    public function getWheelUrl(int $orderId, int $userId): string {
        $token = $this->generateSpinToken($orderId, $userId);

        // Spent spin: no token -> no wheel URL (callers treat '' as "done").
        if ($token === '') {
            return '';
        }

        $base = function_exists('arma_panel_url')
            ? arma_panel_url('lucky-wheel')
            : home_url('/' . trim((string) $this->settings->get('general.panel_slug', 'arma-panel'), '/') . '/lucky-wheel/');

        return add_query_arg([
            'order_id' => $orderId,
            'token'    => rawurlencode($token),
        ], $base);
    }

    /* -----------------------------------------------------------------
     * Atomic spin lock
     * ----------------------------------------------------------------- */

    /**
     * Acquire the per-order spin lock.
     *
     * add_option() fails when the option already exists — that is the
     * atomicity guarantee. Locks older than SPIN_LOCK_TTL seconds (a crashed
     * spin) are broken so a customer is never blocked forever.
     */
    private function acquireSpinLock(int $orderId): bool {
        global $wpdb;

        // Without a database layer there is nothing to lock on (exotic
        // contexts) — the spin proceeds unlocked, canSpin still guards it.
        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'prepare')) {
            return true;
        }

        $key = 'arma_wheel_lock_' . $orderId;

        /*
         * Atomic acquire: INSERT IGNORE silently skips when the row already
         * exists, so ONLY the single request whose insert actually affected
         * a row owns the lock — even under full concurrency (same technique
         * as WP_Upgrader::create_lock() in core). Never call get_option() or
         * delete_option() before owning the lock.
         */
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload)
                 VALUES (%s, %s, 'no')",
                $key,
                (string) time()
            )
        );

        if ((int) $inserted === 1) {
            return true;
        }

        /*
         * A row exists: either a live concurrent spin or the leftover lock of
         * a crashed one. Take a CRASHED lock over atomically — the guarded
         * UPDATE only succeeds when the stored timestamp is older than the
         * TTL, so two racing takeovers can never both win.
         */
        $takenOver = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options}
                 SET option_value = %s
                 WHERE option_name = %s
                   AND option_value + 0 < %d",
                (string) time(),
                $key,
                time() - self::SPIN_LOCK_TTL
            )
        );

        return (int) $takenOver === 1;
    }

    private function releaseSpinLock(int $orderId): void {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'prepare')) {
            return;
        }

        $key = 'arma_wheel_lock_' . $orderId;

        // Direct row delete — the lock must never outlive its owner.
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", $key)
        );

        // Keep the options cache in sync when an object cache is present.
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($key, 'options');
            wp_cache_delete('alloptions', 'options');
        }
    }

    /* -----------------------------------------------------------------
     * HPOS-safe order meta helpers
     * ----------------------------------------------------------------- */

    private function getOrder(int $orderId): ?WC_Order {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($orderId);

        return $order instanceof WC_Order ? $order : null;
    }

    /**
     * Read an order meta value through the WooCommerce order API, falling
     * back to legacy postmeta when the CRUD value is empty (pre-migration
     * data on a freshly switched HPOS store).
     */
    private function readOrderMeta(int $orderId, string $key): string {
        $order = $this->getOrder($orderId);
        if ($order instanceof WC_Order) {
            $value = (string) $order->get_meta($key);
            if ($value !== '') {
                return $value;
            }
        }

        if (function_exists('get_post_meta')) {
            return (string) get_post_meta($orderId, $key, true);
        }

        return '';
    }

    /**
     * Write an order meta value with the WooCommerce CRUD API
     * (update_meta_data + save) — never post-meta functions.
     */
    private function writeOrderMeta(int $orderId, string $key, string $value): void {
        $order = $this->getOrder($orderId);
        if ($order instanceof WC_Order) {
            $order->update_meta_data($key, $value);
            $order->save();

            return;
        }

        if (function_exists('update_post_meta')) {
            update_post_meta($orderId, $key, $value);
        }
    }
}
