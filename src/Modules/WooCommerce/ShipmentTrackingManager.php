<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\Notifications\NotificationService;
use Arma\ArmaPanel\Modules\SMS\SMSManager;
use Throwable;
use WC_Order;

/**
 * Shipment Tracking Manager.
 *
 * Adds an "ارسال و رهگیری مرسوله" box to the WooCommerce order detail page
 * (WooCommerce → سفارش‌ها → انتخاب سفارش). The admin types the shipping
 * method as free text (e.g. «پست پیشتاز») and enters the tracking code.
 * On save:
 *  - The customer ALWAYS receives a panel notification (if the module is on).
 *  - If the WooCommerce SMS system is active, the tracking SMS pattern
 *    «مشتری عزیز سفارش شما توسط {0} ارسال شد ، کد رهگیری {1}» is sent too.
 */
class ShipmentTrackingManager {

    public const META_METHOD    = '_arma_tracking_method';
    public const META_CODE      = '_arma_tracking_code';
    public const META_SMS_SENT  = '_arma_tracking_sms_sent';
    public const META_SMS_ERROR = '_arma_tracking_sms_error';
    public const META_NOTIFIED  = '_arma_tracking_notified';

    private SettingsManager $settings;
    private SMSManager $smsManager;
    private NotificationService $notificationService;

    public function __construct(
        SettingsManager $settings,
        SMSManager $smsManager,
        NotificationService $notificationService
    ) {
        $this->settings            = $settings;
        $this->smsManager          = $smsManager;
        $this->notificationService = $notificationService;
    }

    public function registerHooks(): void {
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post_shop_order', [$this, 'handleSave'], 10, 1);
        // High-Performance Order Storage (HPOS) support
        add_action('woocommerce_process_shop_order_meta', [$this, 'handleSaveHpos'], 10, 2);
    }

    /**
     * Is the whole tracking feature active? (module toggle)
     */
    public function isFeatureEnabled(): bool {
        return $this->settings->isModuleEnabled('order_sms');
    }

    /**
     * Should the tracking info ALSO go out by SMS?
     */
    public function isSmsActive(): bool {
        return $this->isFeatureEnabled()
            && (bool) $this->settings->get('wc_sms.enabled', true)
            && (bool) $this->settings->get('wc_sms.shipping_sms_enabled', true);
    }

    /* -----------------------------------------------------------------
     * Meta box
     * ----------------------------------------------------------------- */

    public function registerMetaBox(): void {
        if (!$this->isFeatureEnabled()) {
            return;
        }

        // Register on BOTH screens for maximum compatibility (classic + HPOS);
        // WordPress simply ignores the screen that is not being viewed.
        add_meta_box(
            'arma_shipment_tracking',
            __('ارسال و رهگیری مرسوله (آرما پنل)', 'arma-panel'),
            [$this, 'renderMetaBox'],
            ['shop_order', 'woocommerce_page_wc-orders'],
            'side',
            'high'
        );
    }

    /**
     * Metabox renderer. Deliberately UN-typed: classic orders pass a WP_Post,
     * HPOS may pass other order representations — never fatal on any of them.
     */
    public function renderMetaBox($post): void {
        try {
            $orderId = 0;

            if (is_object($post)) {
                $orderId = method_exists($post, 'get_id') ? (int) $post->get_id() : (int) ($post->ID ?? 0);
            } elseif (is_numeric($post)) {
                $orderId = (int) $post;
            }

            if ($orderId <= 0 && function_exists('get_the_ID')) {
                $orderId = (int) get_the_ID();
            }

            $this->renderBox($this->resolveOrder($orderId));
        } catch (Throwable $e) {
            $this->log('metabox_error', $e->getMessage());
            echo '<p style="color:#b91c1c;">' . esc_html__('خطا در نمایش بخش رهگیری مرسوله.', 'arma-panel') . '</p>';
        }
    }

    private function resolveOrder(int $orderId): ?WC_Order {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($orderId);

        return $order instanceof WC_Order ? $order : null;
    }

    private function renderBox(?WC_Order $order): void {
        if (!$order || !function_exists('wc_get_order')) {
            echo '<p style="color:#64748b;">' . esc_html__('سفارش برای نمایش اطلاعات ارسال یافت نشد.', 'arma-panel') . '</p>';

            return;
        }

        $orderId  = $order->get_id();
        $method   = (string) $order->get_meta(self::META_METHOD);
        $code     = (string) $order->get_meta(self::META_CODE);
        $smsSent   = (string) $order->get_meta(self::META_SMS_SENT);
        $smsError  = (string) $order->get_meta(self::META_SMS_ERROR);
        $notified  = (string) $order->get_meta(self::META_NOTIFIED);

        wp_nonce_field('arma_save_tracking', 'arma_tracking_nonce');
        ?>
        <style>
            .arma-track-box { direction: rtl; text-align: right; }
            .arma-track-box label { display: block; font-weight: 600; margin: 10px 0 4px; }
            .arma-track-box select, .arma-track-box input[type="text"] { width: 100%; max-width: 100%; }
            .arma-track-status { margin-top: 10px; font-size: 12px; line-height: 1.8; }
            .arma-track-status span { display: inline-block; padding: 2px 9px; border-radius: 10px; margin-left: 4px; font-weight: 600; }
            .arma-track-on { background: #ecfdf5; color: #047857; }
            .arma-track-off { background: #f1f5f9; color: #475569; }
        </style>
        <div class="arma-track-box">
            <label for="arma_tracking_method"><?php esc_html_e('روش حمل (اختیاری — دستی):', 'arma-panel'); ?></label>
            <input type="text" name="arma_tracking_method" id="arma_tracking_method"
                   value="<?php echo esc_attr($method); ?>" placeholder="<?php esc_attr_e('مثال: پست پیشتاز، تیپاکس، باربری...', 'arma-panel'); ?>">

            <label for="arma_tracking_code"><?php esc_html_e('کد رهگیری مرسوله:', 'arma-panel'); ?></label>
            <input type="text" name="arma_tracking_code" id="arma_tracking_code"
                   value="<?php echo esc_attr($code); ?>" placeholder="مثال: 123456789012345678" style="direction: ltr; text-align: left;">

            <p class="arma-track-status">
                <strong><?php esc_html_e('وضعیت:', 'arma-panel'); ?></strong><br>
                <?php if ($notified): ?>
                    <span class="arma-track-on"><?php esc_html_e('اطلاعیه برای مشتری ارسال شد', 'arma-panel'); ?></span>
                <?php else: ?>
                    <span class="arma-track-off"><?php esc_html_e('اطلاعیه ارسال نشده', 'arma-panel'); ?></span>
                <?php endif; ?>
                <?php if ($smsSent): ?>
                    <span class="arma-track-on"><?php esc_html_e('پیامک ارسال شد', 'arma-panel'); ?></span>
                <?php endif; ?>
                <?php if ($smsError !== ''): ?>
                    <span class="arma-track-err" style="background:#fef2f2;color:#b91c1c;"><?php esc_html_e('خطای پیامک:', 'arma-panel'); ?></span><br>
                    <span style="color:#b91c1c;"><?php echo esc_html($smsError); ?></span>
                <?php endif; ?>
            </p>

            <?php if ($this->isSmsActive()): ?>
                <p style="font-size: 12px; color: #64748b; margin: 8px 0 0;">
                    <?php esc_html_e('با ذخیره: اگر روش حملی وارد شده باشد پترن «توسط {روش حمل} ارسال شد ، کد رهگیری {کد}» و اگر خالی باشد پترن «فقط کد رهگیری» ارسال می‌شود. اگر پترن ارسال مرسوله در تنظیمات خالی باشد، همیشه از پترن «فقط کد رهگیری» استفاده می‌شود.', 'arma-panel'); ?>
                </p>
            <?php endif; ?>

            <?php submit_button(__('ذخیره اطلاعات ارسال', 'arma-panel'), 'primary', 'arma_tracking_save'); ?>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------
     * Save + notify
     * ----------------------------------------------------------------- */

    public function handleSave(int $orderId): void {
        $this->save((int) $orderId);
    }

    /**
     * HPOS hook callback. The $post argument is deliberately UN-typed:
     * WooCommerce's HPOS storage passes NULL here (a HPOS order has no
     * WP_Post), and a typed \WP_Post parameter would throw a TypeError —
     * the "critical error on this site" that killed the order-edit save
     * (status change AND tracking save alike). Fixed in v1.3.1.
     */
    public function handleSaveHpos(int $orderId, $post = null): void {
        $this->save((int) $orderId);
    }

    /**
     * Never let our box take down the WooCommerce order save: any failure is
     * logged and swallowed so the admin flow always completes.
     */
    public function save(int $orderId): void {
        try {
            $this->doSave($orderId);
        } catch (Throwable $e) {
            $this->log('save_error', $e->getMessage());
        }
    }

    private function doSave(int $orderId): void {
        if (!$this->isFeatureEnabled()) {
            return;
        }

        if (($_POST['arma_tracking_nonce'] ?? '') === '') {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['arma_tracking_nonce']));
        if (!wp_verify_nonce($nonce, 'arma_save_tracking')) {
            return;
        }

        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            return;
        }

        $order = $this->resolveOrder($orderId);
        if (!$order) {
            return;
        }

        // Free-text shipping method (admin types it, e.g. «پست پیشتاز») — optional
        $method = sanitize_text_field(wp_unslash($_POST['arma_tracking_method'] ?? ''));
        $code   = sanitize_text_field(wp_unslash($_POST['arma_tracking_code'] ?? ''));

        if ($code === '') {
            return; // a tracking code is required; the shipping method is optional
        }

        // Skip duplicate notifications for identical data
        $alreadyMethod = (string) $order->get_meta(self::META_METHOD);
        $alreadyCode   = (string) $order->get_meta(self::META_CODE);
        $alreadyNotified = (string) $order->get_meta(self::META_NOTIFIED);
        $isDuplicate = ($alreadyNotified !== '' && $alreadyMethod === $method && $alreadyCode === $code);

        $order->update_meta_data(self::META_METHOD, $method);
        $order->update_meta_data(self::META_CODE, $code);
        $order->save();

        if ($isDuplicate) {
            return;
        }

        // 1) Panel notification for the customer (always, while module is on)
        $this->notifyCustomer($order, $method, $code);

        // 2) Tracking SMS (only when the SMS system is active)
        if ($this->isSmsActive()) {
            $this->sendTrackingSms($order, $method, $code);
        }
    }

    private function notifyCustomer(WC_Order $order, string $method, string $code): void {
        try {
            $userId = (int) $order->get_customer_id();
            if ($userId <= 0) {
                return;
            }

            $codeHtml = '<strong dir="ltr">' . esc_html($code) . '</strong>';
            $orderNum = esc_html((string) $order->get_order_number());

            if ($method !== '') {
                $message = sprintf(
                    /* translators: 1: shipping method, 2: tracking code, 3: order number */
                    __('مشتری عزیز، سفارش شما ( #%3$s ) توسط %1$s ارسال شد. کد رهگیری مرسوله: %2$s', 'arma-panel'),
                    '<strong>' . esc_html($method) . '</strong>',
                    $codeHtml,
                    $orderNum
                );
            } else {
                $message = sprintf(
                    /* translators: 1: tracking code, 2: order number */
                    __('مشتری عزیز، سفارش شما ( #%2$s ) ارسال شد. کد رهگیری مرسوله: %1$s', 'arma-panel'),
                    $codeHtml,
                    $orderNum
                );
            }

            $this->notificationService->create(
                $userId,
                __('سفارش شما ارسال شد', 'arma-panel'),
                $message,
                'system'
            );

            $order->update_meta_data(self::META_NOTIFIED, current_time('mysql'));
            $order->save();
        } catch (Throwable $e) {
            $this->log('notify_failed', $e->getMessage());
        }
    }

    private function sendTrackingSms(WC_Order $order, string $method, string $code): void {
        try {
            $phone = (string) $order->get_billing_phone();
            if ($phone === '') {
                $userId = (int) $order->get_customer_id();
                if ($userId > 0) {
                    $phone = (string) get_user_meta($userId, 'arma_phone', true);
                }
            }

            if ($phone === '') {
                return;
            }

            $result = $this->smsManager->sendShippingTracking($phone, $method, $code);

            if ($result->isSuccessful()) {
                $order->update_meta_data(self::META_SMS_SENT, current_time('mysql'));
                $order->update_meta_data(self::META_SMS_ERROR, '');
                $order->save();
            } else {
                // Surface the failure in the box (v1.3.2): a silent SMS failure
                // is indistinguishable from "never attempted" for the admin.
                $reason = $result->getErrorCode() . ' — ' . $result->getMessage();
                $order->update_meta_data(self::META_SMS_ERROR, mb_substr($reason, 0, 200));
                $order->save();
            }
        } catch (Throwable $e) {
            $this->log('sms_failed', $e->getMessage());
        }
    }

    private function log(string $context, string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ArmaPanel Tracking/' . $context . '] ' . $message);
        }
    }
}
