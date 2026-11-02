<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\Notifications\NotificationService;
use Arma\ArmaPanel\Modules\SMS\SMSManager;
use Throwable;
use WC_Order;

/**
 * Abandoned-Payment Reminder (scope changed in v1.5.0).
 *
 * ONLY for REGISTERED customers whose order reached the payment gateway and
 * was left un-paid ("pending" / "on-hold") past the admin-configurable window
 * (wc_sms.abandoned_remind_hours, default 24h):
 *  - creates a personal single-use discount coupon (percent set by the admin),
 *  - sends the reminder SMS pattern («... کد تخفیف {0} ...»),
 *  - records a panel notification.
 *
 * Cart-activity tracking was REMOVED by design: merely adding products to
 * the cart is NOT a reminder trigger anymore — only a real registered order
 * stuck awaiting payment is.
 *
 * Reminders are cooldown-gated (v1.10.2): a customer who was reminded is
 * skipped until wc_sms.abandoned_cooldown_days (default 30) has passed —
 * so a repeat abandonment after the window earns a fresh reminder, but
 * nobody can be spammed. Each ORDER is still reminded at most once, ever
 * (order-level flag), regardless of the user cooldown.
 *
 * Runs ONLY from WP-Cron (hourly sweep). the sweep is never
 * executed inside ordinary visitor requests anymore — it made every page
 * view potentially run a full order query. For hosts where WP-Cron is
 * disabled (DISABLE_WP_CRON), an admin-driven fallback schedules single
 * events non-blockingly instead. Everything is gated by the WooCommerce
 * SMS module and its own enable toggle.
 *
 * the sweep targets PENDING orders by default (awaiting
 * payment). "failed" and "on-hold" can be ADDED by the admin via settings
 * (wc_sms.abandoned_include_failed / wc_sms.abandoned_include_onhold,
 * both default OFF) — on-hold is no longer swept implicitly.
 */
class AbandonedCartManager {

    public const CRON_HOOK          = 'arma_panel_abandoned_check';
    public const ORDER_META_REMIND  = '_arma_pending_reminded';
    public const USER_META_REMINDED = '_arma_abandoned_reminded'; // timestamp of the last reminder (cooldown-gated)
    private const SWEEP_LOCK        = 'arma_abandoned_sweep_lock'; // 15-min throttle for the traffic-driven sweep

    private SettingsManager $settings;
    private SMSManager $smsManager;
    private NotificationService $notificationService;

    public function __construct(SettingsManager $settings, SMSManager $smsManager, NotificationService $notificationService) {
        $this->settings            = $settings;
        $this->smsManager          = $smsManager;
        $this->notificationService = $notificationService;
    }

    public function registerHooks(): void {
        add_action(self::CRON_HOOK, [$this, 'runSweep']);

        /*
         * the sweep is cron-only now. Ordinary visitor requests
         * (template_redirect) NEVER run it. For hosts that disabled WP-Cron
         * (DISABLE_WP_CRON), an admin_init fallback keeps the feature alive:
         * it just schedules single events — non-blocking, no sweep in-page.
         */
        add_action('admin_init', [$this, 'maybeScheduleFallbackForDisabledCron']);

        $this->ensureSchedule();
    }

    /**
     * Schedule/refresh the hourly cron event. Short first interval (10 min)
     * so admin-configured windows like 1 hour are honoured promptly; stale
     * far-future events from older releases are re-scheduled tighter.
     */
    private function ensureSchedule(): void {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        $next = wp_next_scheduled(self::CRON_HOOK);

        if ($next === false) {
            wp_schedule_event(time() + 600, 'hourly', self::CRON_HOOK);
            return;
        }

        if (is_numeric($next) && ((int) $next - time()) > 3700 && function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_schedule_event(time() + 600, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Non-blocking fallback for DISABLE_WP_CRON hosts: on admin visits,
     * schedule a single near-future sweep event when none is pending.
     *
     * Scheduling an event costs one option write — the sweep itself never
     * runs inside the visitor's/admin's request here.
     */
    public function maybeScheduleFallbackForDisabledCron(): void {
        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            return;
        }

        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) {
            return;
        }

        if (get_transient(self::SWEEP_LOCK) !== false) {
            return; // a fallback event was scheduled recently
        }

        set_transient(self::SWEEP_LOCK, time(), 900);
        wp_schedule_single_event(time() + 60, self::CRON_HOOK);
    }

    /**
     * which order statuses the sweep targets. PENDING only by
     * default; "failed" and "on-hold" are opt-in via settings.
     *
     * @return array<int, string>
     */
    public function getSweepStatuses(): array {
        $statuses = ['pending'];

        if ((bool) $this->settings->get('wc_sms.abandoned_include_failed', false)) {
            $statuses[] = 'failed';
        }

        if ((bool) $this->settings->get('wc_sms.abandoned_include_onhold', false)) {
            $statuses[] = 'on-hold';
        }

        return $statuses;
    }

    public function isFeatureEnabled(): bool {
        return $this->settings->isModuleEnabled('order_sms')
            && (bool) $this->settings->get('wc_sms.abandoned_enabled', true);
    }

    /**
     * Admin-configurable reminder window in seconds
     * (wc_sms.abandoned_remind_hours, default 24h, hourly cron granularity).
     */
    public function getRemindAfterSeconds(): int {
        $hours = max(1, min(720, (int) $this->settings->get('wc_sms.abandoned_remind_hours', 24)));

        return $hours * 3600;
    }

    /**
     * Minimum gap between TWO reminders for the SAME customer
     * (wc_sms.abandoned_cooldown_days, default 30 days).
     */
    public function getRemindCooldownSeconds(): int {
        $days = max(1, min(365, (int) $this->settings->get('wc_sms.abandoned_cooldown_days', 30)));

        return $days * 86400;
    }

    /**
     * True while the customer's last reminder is younger than the cooldown
     * window (or their flag is unreadable — fail safe: treat as reminded).
     */
    private function isUserInCooldown(int $userId): bool {
        $last = (string) get_user_meta($userId, self::USER_META_REMINDED, true);

        if ($last === '') {
            return false; // never reminded
        }

        $ts = strtotime($last);

        return $ts === false || (time() - $ts) < $this->getRemindCooldownSeconds();
    }

    /* -----------------------------------------------------------------
     * The sweep
     * ----------------------------------------------------------------- */

    /**
     * Activation clock (v1.8.0): the moment the admin (re-)enabled the
     * reminder feature. Orders created BEFORE it are invisible to the sweep —
     * enabling the module must never mass-SMS every historical pending order.
     *
     * @return int unix timestamp, 0 when no clock is recorded
     */
    public function getActivationClock(): int {
        $clock = (int) $this->settings->get('wc_sms.abandoned_enabled_at', 0);

        if ($clock <= 0 && $this->isFeatureEnabled()) {
            // Upgraded install with the feature already ON and no clock saved
            // (pre-1.8.0): start counting from NOW so old orders stay silent.
            $clock = time();
            $this->settings->set('wc_sms.abandoned_enabled_at', $clock);
            $this->settings->save();
            $this->log('activation clock missing — recorded now (' . $clock . '), historical orders skipped');
        }

        return $clock;
    }

    /**
     * @return int number of reminders sent
     */
    public function runSweep(): int {
        if (!$this->isFeatureEnabled()) {
            return 0;
        }

        $clock = $this->getActivationClock();
        if ($clock <= 0) {
            return 0;
        }

        return $this->sweepPendingOrders($clock);
    }

    /**
     * Orders stuck in an "awaiting payment" state (PENDING by default —
     * failed/on-hold opt-in) past the configured window, with a
     * registered customer that has not been reminded yet.
     *
     * v1.8.0: only orders CREATED AFTER the activation clock are considered —
     * the cutoff is applied inside the order query itself (DB-level) so the
     * sweep never even loads historical orders.
     */
    private function sweepPendingOrders(int $clock): int {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $sent = 0;

        try {
            /*
             * The sweep fetches only order IDS first ('return' => 'ids') and
             * hydrates ONE order at a time while processing: full order
             * objects are the expensive part (HPOS or postmeta reads), and
             * most candidates get skipped anyway (guest / cooldown /
             * reminded) — hydrating whole pages upfront was waste.
             *
             * Already-reminded orders are excluded inside the query (meta
             * NOT EXISTS), and results are paged (5 x 50) so a pile of
             * guest / already-handled pending orders can no longer hide
             * newer eligible orders.
             */
            for ($page = 1; $page <= 5; $page++) {
                $orderIds = wc_get_orders([
                    'status'  => $this->getSweepStatuses(),
                    'limit'   => 50,
                    'paged'   => $page,
                    'orderby' => 'modified',
                    'order'   => 'ASC',
                    'return'  => 'ids',
                    // orders created before the feature was enabled are excluded
                    // by the database query itself (old pending orders would
                    // otherwise instantly match the reminder window)
                    'date_created' => '>=' . $clock,
                    // orders already carrying a reminder are filtered out by
                    // the database — re-reading them every sweep was waste
                    'meta_query' => [
                        [
                            'key'     => self::ORDER_META_REMIND,
                            'compare' => 'NOT EXISTS',
                        ],
                    ],
                ]);

                if ($orderIds === []) {
                    break; // no more pages
                }

                foreach ($orderIds as $orderId) {
                    $order = wc_get_order((int) $orderId);

                    if (!$order instanceof WC_Order) {
                        continue;
                    }

                    $userId = (int) $order->get_customer_id();
                    if ($userId <= 0) {
                        continue; // guests cannot be reminded (no account/phone binding)
                    }

                    if ($this->isUserInCooldown($userId)) {
                        continue; // reminded recently — inside the cooldown window
                    }

                    if ((string) $order->get_meta(self::ORDER_META_REMIND) !== '') {
                        continue; // already reminded for this order (defense in depth)
                    }

                    // Defense in depth: never remind an order that predates the
                    // activation clock, even if a query layer ignored the filter.
                    $created = method_exists($order, 'get_date_created') ? $order->get_date_created() : null;
                    if ($created && $created->getTimestamp() < $clock) {
                        continue;
                    }

                    $modified = $order->get_date_modified();
                    if (!$modified || (time() - $modified->getTimestamp()) < $this->getRemindAfterSeconds()) {
                        continue;
                    }

                    if ($this->remind($userId, sprintf('یادآوری تکمیل سفارش #%d', $order->get_id()))) {
                        $order->update_meta_data(self::ORDER_META_REMIND, current_time('mysql'));
                        $order->save();
                        // cooldown anchor: timestamp of the LAST reminder (v1.10.2)
                        update_user_meta($userId, self::USER_META_REMINDED, current_time('mysql'));
                        $sent++;
                    }
                }
            }
        } catch (Throwable $e) {
            $this->log('pending sweep failed: ' . $e->getMessage());
        }

        return $sent;
    }

    /* -----------------------------------------------------------------
     * Reminder (coupon + SMS + notification)
     * ----------------------------------------------------------------- */

    /**
     * @param int    $userId
     * @param string $internalNote
     * @return bool true when the reminder (coupon+notification) was issued
     */
    private function remind(int $userId, string $internalNote): bool {
        try {
            $percent    = max(1, min(100, (int) $this->settings->get('wc_sms.abandoned_percent', 10)));
            $expiryDays = max(1, min(365, (int) $this->settings->get('wc_sms.abandoned_expiry_days', 7)));

            $code = CouponService::generate(
                'ARMACART',
                (float) $percent,
                'percent',
                $expiryDays,
                $userId,
                $internalNote
            );

            if ($code === '') {
                return false;
            }

            // 1) Panel notification (always)
            $this->notificationService->create(
                $userId,
                __('سبد خرید شما منتظر شماست! 🛒', 'arma-panel'),
                sprintf(
                    /* translators: 1: percent, 2: coupon code */
                    __('مشتری عزیز، سبد خرید شما رها شده است! با کد تخفیف %1$s٪ ویژه شما («%2$s») خرید خود را تکمیل کنید. این کد فقط برای شما و یک‌بارمصرف است.'),
                    (string) $percent,
                    '<strong dir="ltr">' . esc_html($code) . '</strong>'
                ),
                'system'
            );

            // 2) SMS (when a pattern is configured)
            $this->sendReminderSms($userId, $code, $percent);

            return true;
        } catch (Throwable $e) {
            $this->log('remind failed: ' . $e->getMessage());

            return false;
        }
    }

    private function sendReminderSms(int $userId, string $code, int $percent): void {
        try {
            $phone = (string) get_user_meta($userId, 'arma_phone', true);
            if ($phone === '') {
                $phone = (string) get_user_meta($userId, 'billing_phone', true);
            }

            if ($phone === '') {
                return;
            }

            /*
             * Pattern resolution is FULLY delegated to the SMS manager.
             * (v1.4.0: this used to pre-check (int) abandoned_pattern and
             * silently skip the send for non-numeric pattern identifiers —
             * Kavenegar template names / FarazSMS pattern codes never went
             * out. The manager handles every provider id + NO_PATTERN.)
             */
            $this->smsManager->sendAbandonedCartReminder($phone, $code, $percent);
        } catch (Throwable $e) {
            $this->log('sms failed: ' . $e->getMessage());
        }
    }

    private function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ArmaPanel AbandonedCart] ' . $message);
        }
    }
}
