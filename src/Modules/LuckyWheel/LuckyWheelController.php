<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\LuckyWheel;

/**
 * Controller for Lucky Wheel interactions and order redirect.
 */
class LuckyWheelController {

    private LuckyWheelService $service;

    public function __construct(LuckyWheelService $service) {
        $this->service = $service;
    }

    public function registerHooks(): void {
        add_action('wp_ajax_arma_spin_wheel', [$this, 'handleSpin']);
        add_action('wp_ajax_nopriv_arma_spin_wheel', [$this, 'handleSpin']);

        /*
         * Redirect to the wheel from template_redirect (fires BEFORE any theme
         * output). Redirecting from woocommerce_thankyou is unreliable because
         * headers may already be sent at that point, silently killing the
         * redirect.
         */
        add_action('template_redirect', [$this, 'handleOrderRedirect'], 5);
    }

    /**
     * Handle AJAX spin request.
     */
    public function handleSpin(): void {
        check_ajax_referer('arma_wheel_nonce', 'nonce');

        $orderId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
        $token   = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $userId  = get_current_user_id();

        $result = $this->service->spin($orderId, $userId, $token);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Redirect the customer from the WooCommerce "order received" page to the
     * Lucky Wheel, immediately after a successful payment.
     *
     * UNCONDITIONAL while the wheel module is on (v1.5.0): no separate
     * on/off setting — a successful purchase always goes to the wheel unless
     * the customer already spent this order's spin.
     *
     * a bare order ID is no longer enough —
     *   - the request must carry a VALID order key (?key=…);
     *   - the order must be PAID (pending/on-hold never redirect);
     *   - the visitor must be logged in AND own the order.
     */
    public function handleOrderRedirect(): void {
        if (!$this->service->isEnabled()) {
            return;
        }

        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return;
        }

        // Prevent redirect loops / repeated transfers
        if (isset($_GET['arma_wheel_redirected'])) {
            return;
        }

        $orderId = $this->getOrderIdFromRequest();
        if ($orderId <= 0) {
            return;
        }

        // Only redirect for a real order
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : null;
        if (!$order instanceof \WC_Order) {
            return;
        }

        // the order-received URL must carry a key that really
        // belongs to THIS order — otherwise anyone could trigger the wheel
        // redirect for someone else's order by probing IDs.
        if (!$this->orderKeyIsValid($order, $orderId)) {
            return;
        }

        // Failed / cancelled / refunded purchases are NOT a "successful buy".
        if (method_exists($order, 'get_status')) {
            $status = (string) $order->get_status();
            if (in_array($status, ['failed', 'cancelled', 'refunded'], true)) {
                return;
            }
        }

        // only PAID orders go to the wheel (pending / on-hold are
        // still awaiting payment).
        if (!$this->service->isOrderPaid($order)) {
            return;
        }

        // Guests cannot use the wheel — a real logged-in session is required.
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        // Ownership check: the order must belong to the current user
        if ((int) $order->get_customer_id() !== $userId) {
            return;
        }

        // Already spun for this order? Then the thank-you page stays as-is.
        // (HPOS-safe read: order meta API first, legacy postmeta fallback.)
        if ($this->readSpunFlag($order, $orderId) === '1') {
            return;
        }

        $wheelUrl = $this->service->getWheelUrl($orderId, $userId);

        if (empty($wheelUrl)) {
            return;
        }

        wp_safe_redirect($wheelUrl);
        exit;
    }

    /**
     * Resolve the order ID from the order-received request.
     */
    private function getOrderIdFromRequest(): int {
        global $wp_query;

        $orderId = 0;

        if (is_object($wp_query) && method_exists($wp_query, 'get')) {
            $orderId = (int) $wp_query->get('order-received', 0);
        }

        if ($orderId <= 0 && isset($_GET['order-received'])) {
            $orderId = (int) $_GET['order-received'];
        }

        return $orderId;
    }

    /**
     * Validate the order key of an order-received request.
     *
     * without a valid key the request is not a legitimate
     * thank-you visit and must never be redirected to the wheel.
     */
    private function orderKeyIsValid(\WC_Order $order, int $orderId): bool {
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

        if ($key === '') {
            return false;
        }

        if (method_exists($order, 'key_is_valid')) {
            return (bool) $order->key_is_valid($key);
        }

        if (function_exists('wc_get_order_id_by_order_key')) {
            return (int) wc_get_order_id_by_order_key($key) === $orderId;
        }

        return false;
    }

    /**
     * Read the wheel "already spun" flag for an order — HPOS-safe order-meta
     * read with a legacy postmeta fallback.
     */
    private function readSpunFlag(\WC_Order $order, int $orderId): string {
        $value = method_exists($order, 'get_meta') ? (string) $order->get_meta('_arma_wheel_spun') : '';

        if ($value === '' && function_exists('get_post_meta')) {
            $value = (string) get_post_meta($orderId, '_arma_wheel_spun', true);
        }

        return $value;
    }
}
