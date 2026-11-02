<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\SMS\SMSManager;
use WC_Order;
use Throwable;

/**
 * WooCommerce integration manager.
 * Handles /my-account/ redirection and order payment SMS hooks.
 */
class WooCommerceManager {

    private SettingsManager $settings;
    private SMSManager $smsManager;
    private OrderService $orderService;
    private AddressService $addressService;

    public function __construct(
        SettingsManager $settings,
        SMSManager $smsManager,
        OrderService $orderService,
        AddressService $addressService
    ) {
        $this->settings       = $settings;
        $this->smsManager     = $smsManager;
        $this->orderService   = $orderService;
        $this->addressService = $addressService;
    }

    /**
     * Register WooCommerce hooks.
     */
    public function registerHooks(): void {
        // 1. My Account redirect
        add_action('template_redirect', [$this, 'redirectMyAccountPage']);

        // 2. Order paid event
        add_action('woocommerce_payment_complete', [$this, 'handleOrderPaid'], 20, 1);
        add_action('woocommerce_order_status_processing', [$this, 'handleOrderPaid'], 20, 1);
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderPaid'], 20, 1);

        // 3. personal prize coupons are valid for their WINNER only.
        add_filter('woocommerce_coupon_is_valid', [$this, 'validateCouponOwner'], 10, 2);
    }

    /**
     * Personal prize coupons (carrying the _arma_user_id meta written by
     * CouponService::generate) must never be redeemed by anyone except the
     * winner they were issued to.
     *
     * WooCommerce turns a thrown Exception from this filter into a clean,
     * translatable checkout error.
     *
     * @param bool $valid
     * @param \WC_Coupon $coupon
     * @return bool
     */
    public function validateCouponOwner($valid, $coupon) {
        if (!is_object($coupon) || !method_exists($coupon, 'get_meta')) {
            return $valid;
        }

        $ownerId = (int) $coupon->get_meta('_arma_user_id');

        if ($ownerId <= 0) {
            return $valid; // not a personal Arma coupon — nothing to enforce
        }

        $currentUserId = function_exists('get_current_user_id') ? get_current_user_id() : 0;

        if ($currentUserId !== $ownerId) {
            throw new \Exception(
                __('این کد تخفیف مخصوص شما نیست.', 'arma-panel')
            );
        }

        return $valid;
    }

    /**
     * Redirect the WooCommerce "My Account" page (/my-account/) to the Arma
     * Panel — for EVERYONE (customers AND administrators). Admins keep full
     * access to wp-admin; only the frontend account page is replaced.
     * Also covers custom theme account pages built with the
     * [woocommerce_my_account] shortcode.
     *
     * This behaviour is UNCONDITIONAL by design (v1.3.0): the Arma Panel IS
     * the account page, so no on/off setting exists anymore.
     */
    public function redirectMyAccountPage(): void {
        // Never interfere with background requests.
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return;
        }

        // 1. Native WooCommerce account page?
        $isAccountPage = function_exists('is_account_page') && is_account_page();

        // 2. Custom page using the WooCommerce "My Account" shortcode?
        if (!$isAccountPage && function_exists('is_singular') && is_singular('page')) {
            $post = function_exists('get_post') ? get_post() : null;
            if ($post instanceof \WP_Post && function_exists('has_shortcode')
                && has_shortcode((string) $post->post_content, 'woocommerce_my_account')
            ) {
                $isAccountPage = true;
            }
        }

        if (!$isAccountPage) {
            return;
        }

        // Do not redirect password-reset / logout flows (they must finish).
        if (function_exists('is_wc_endpoint_url') && (is_wc_endpoint_url('lost-password') || is_wc_endpoint_url('customer-logout'))) {
            return;
        }

        // Loop guard: if the merchant assigned the WC account page to the
        // Arma Panel page itself, never redirect to itself.
        if (function_exists('wc_get_page_id') && function_exists('get_option')) {
            $myAccountId  = (int) wc_get_page_id('myaccount');
            $panelPageId  = (int) get_option('arma_panel_page_id', 0);
            if ($myAccountId > 0 && $myAccountId === $panelPageId) {
                return;
            }
        }

        $panelUrl = function_exists('arma_panel_url')
            ? arma_panel_url('dashboard')
            : home_url('/' . trim((string) $this->settings->get('general.panel_slug', 'arma-panel'), '/') . '/');

        wp_safe_redirect($panelUrl);
        exit;
    }

    /**
     * Handle payment completion side-effect (SMS notification).
     *
     * @param int $orderId
     */
    public function handleOrderPaid(int $orderId): void {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        // Order SMS is a module: nothing at all when it is switched off.
        if (!$this->settings->isModuleEnabled('order_sms')) {
            return;
        }

        // Prevent duplicate SMS sends per order (HPOS-safe: order meta API —
        // HPOS orders are not posts, so post-meta functions must not be used)
        $sentKey = '_arma_order_sms_sent';
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }

        /*
         * honour the admin-configured trigger status
         * (wc_sms.trigger_status — 'processing' default, or 'completed').
         * With 'completed' the SMS only goes out once the order actually
         * reaches "completed", never on payment/processing.
         */
        $triggerStatus = (string) $this->settings->get('wc_sms.trigger_status', 'processing');
        if ($triggerStatus === 'completed' && method_exists($order, 'has_status') && !$order->has_status('completed')) {
            return;
        }
        if ((string) $order->get_meta($sentKey) !== '') {
            return;
        }

        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            $user_id = $order->get_customer_id();
            if ($user_id > 0) {
                $phone = (string) get_user_meta($user_id, 'arma_phone', true);
            }
        }

        if (empty($phone)) {
            return;
        }

        try {
            $orderNumber = (string) $order->get_order_number();
            $result = $this->smsManager->sendOrderConfirmation($phone, $orderNumber);

            if ($result->isSuccessful()) {
                $order->update_meta_data($sentKey, current_time('mysql'));
                $order->save();
            }
        } catch (Throwable $e) {
            // SMS failure is non-blocking side-effect!
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ArmaPanel] Failed to send order SMS: ' . $e->getMessage());
            }
        }
    }

    public function getOrderService(): OrderService {
        return $this->orderService;
    }

    public function getAddressService(): AddressService {
        return $this->addressService;
    }
}
