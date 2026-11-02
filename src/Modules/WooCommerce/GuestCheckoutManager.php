<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Arma\ArmaPanel\Core\SettingsManager;
use Throwable;

/**
 * Guest Checkout Manager.
 *
 * Responsibilities:
 * 1. Optional "force login before purchase" policy: guests are redirected
 *    from cart/checkout to the OTP login page and returned (with their cart
 *    preserved) right back to where they were.
 * 2. Server-side guard on checkout processing (no POST bypass).
 * 3. Attractive register banner when the Lucky Wheel is active but login is
 *    not forced ("register and the lucky wheel is yours after each order").
 */
class GuestCheckoutManager {

    public const GUEST_CART_PREFIX = 'arma_guest_cart_';

    private SettingsManager $settings;

    /** @var bool whether the banner was already printed via wp_body_open */
    private bool $bannerRenderedAtBodyOpen = false;

    public function __construct(SettingsManager $settings) {
        $this->settings = $settings;
    }

    public function registerHooks(): void {
        add_action('template_redirect', [$this, 'maybeGateCartAndCheckout'], 20);
        add_action('woocommerce_checkout_process', [$this, 'guardCheckoutProcessing']);

        /*
         * the same "force login" policy must also cover the
         * BLOCK-BASED (Store API) checkout — classic checkout guards do not
         * run there. The Store API validates the thrown RouteException and
         * returns its message to the customer.
         */
        add_action('woocommerce_store_api_checkout_update_order_from_request', [$this, 'guardStoreApiCheckout']);

        add_shortcode('arma_wheel_banner', [$this, 'renderRegisterBannerShortcode']);

        /*
         * Banner stylesheet: enqueued ONLY when the banner will actually be
         * displayed — pages without it never load a single byte of it.
         */
        add_action('wp_enqueue_scripts', [$this, 'enqueueBannerAssets']);

        /*
         * Safety net for the guest-cart token: if a customer is ALREADY
         * logged in but still carries a capture cookie (e.g. they verified
         * the OTP, never finished registration, then signed in some other
         * way), bring the captured cart into their (empty) session.
         *
         * Kept deliberately cheap: the cookie presence check runs FIRST, so
         * normal visitors never pay for anything beyond an isset() check.
         */
        add_action('wp_loaded', [$this, 'maybeRestoreGuestCartOnLoad'], 20);

        /*
         * Banner output — two complementary hooks for maximum theme support:
         *  1) wp_body_open: the standard, best position (top of <body>).
         *  2) wp_footer fallback: many custom themes (and some page-builder
         *     setups) never call wp_body_open(); the banner is then moved to
         *     the top of <body> with one tiny synchronous script, so it shows
         *     on ANY theme (Woodmart, Hello Elementor, Extra, …).
         */
        add_action('wp_body_open', [$this, 'renderBannerAtBodyOpen']);
        add_action('wp_footer', [$this, 'renderBannerFooterFallback']);
    }

    /**
     * Enqueue the banner stylesheet — only when the banner is on.
     */
    public function enqueueBannerAssets(): void {
        if (!self::shouldShowRegisterBanner($this->settings)) {
            return;
        }

        if (function_exists('is_admin') && is_admin()) {
            return;
        }

        wp_enqueue_style(
            'arma-panel-banner',
            ARMA_PANEL_URL . 'assets/css/arma-banner.css',
            [],
            defined('ARMA_PANEL_VERSION') ? ARMA_PANEL_VERSION : '1.0.0'
        );
    }

    /**
     * Primary banner output (standard themes).
     */
    public function renderBannerAtBodyOpen(): void {
        echo $this->renderRegisterBanner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->bannerRenderedAtBodyOpen = true;
    }

    /**
     * Fallback banner output for themes without wp_body_open: render at the
     * footer and immediately relocate it to the top of <body>.
     */
    public function renderBannerFooterFallback(): void {
        if ($this->bannerRenderedAtBodyOpen) {
            return; // already placed by wp_body_open — never duplicate
        }

        $html = $this->renderRegisterBanner();

        if ($html === '') {
            return;
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<script>(function(){var b=document.getElementById(\'arma-wheel-banner\');'
            . 'if(b&&!b.getAttribute(\'data-placed\')){document.body.prepend(b);b.setAttribute(\'data-placed\',\'1\');}})();</script>';
    }

    /* -----------------------------------------------------------------
     * Policy checks (pure logic — unit tested)
     * ----------------------------------------------------------------- */

    public function isForceLoginEnabled(): bool {
        return (bool) $this->settings->get('woocommerce.force_login_checkout', false);
    }

    /**
     * Show the register incentive banner for guests when the banner is on and
     * the Lucky Wheel is active — regardless of whether login-before-purchase
     * is forced or not.
     */
    public static function shouldShowRegisterBanner(SettingsManager $settings): bool {
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return false;
        }

        return $settings->isModuleEnabled('lucky_wheel')
            && (bool) $settings->get('lucky_wheel.enabled', true)
            && (bool) $settings->get('lucky_wheel.show_banner', true);
    }

    /* -----------------------------------------------------------------
     * 1) Gate cart & checkout for guests
     * ----------------------------------------------------------------- */

    /**
     * Redirect guests to the OTP login page when "force login" is enabled,
     * carrying the current URL so they come straight back after login.
     */
    public function maybeGateCartAndCheckout(): void {
        if (!$this->isForceLoginEnabled()) {
            return;
        }

        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return;
        }

        // Never interfere with background/editor requests.
        if ((function_exists('wp_doing_ajax') && wp_doing_ajax())
            || (function_exists('wp_doing_cron') && wp_doing_cron())
            || (function_exists('is_admin') && is_admin())
        ) {
            return;
        }

        $isCart     = function_exists('is_cart') && is_cart();
        $isCheckout = function_exists('is_checkout') && is_checkout();

        if (!$isCart && !$isCheckout) {
            return;
        }

        /*
         * order-received / order-pay endpoints are part of the
         * checkout page but must NEVER be gated — the customer has already
         * paid (or is mid-payment) and bouncing them to the login page would
         * strand them after a successful purchase.
         */
        if ($this->isOrderEndpointRequest()) {
            return;
        }

        $loginUrl = $this->getLoginUrlWithRedirect($this->getCurrentUrl());

        wp_safe_redirect($loginUrl);
        exit;
    }

    /**
     * Is the current request an order-received / order-pay endpoint?
     * (These are exempt from the guest gate — the customer has already paid
     * or is mid-payment.)
     *
     * When WooCommerce's endpoint helper is available it is the ONLY signal:
     * it understands both pretty and plain permalinks. The hand-rolled
     * $_GET fallback runs only when that helper is missing, and then only
     * for plain permalinks AND a request that actually proves entitlement
     * to the order — a bare ?order-pay=123 must NOT smuggle a guest past
     * the gate.
     */
    private function isOrderEndpointRequest(): bool {
        if (function_exists('is_wc_endpoint_url')) {
            return is_wc_endpoint_url('order-received') || is_wc_endpoint_url('order-pay');
        }

        if (!empty($_GET['order-received']) && $this->requestOwnsOrder((int) $_GET['order-received'], 'order-received')) {
            return true;
        }

        if (!empty($_GET['order-pay']) && $this->requestOwnsOrder((int) $_GET['order-pay'], 'order-pay')) {
            return true;
        }

        return false;
    }

    /**
     * Does this request actually own the given order (fallback path only)?
     *
     * order-received: the ?key= order key must match the order.
     * order-pay:      the order key matches OR the current user is the
     *                 order's customer.
     * Everything else (no key, no login, pretty permalinks, WC missing)
     * fails CLOSED — the guest gate stays up.
     */
    private function requestOwnsOrder(int $orderId, string $context): bool {
        // Honour these query vars only with plain permalinks.
        if (function_exists('get_option') && (string) get_option('permalink_structure', '') !== '') {
            return false;
        }

        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return false;
        }

        $order = wc_get_order($orderId);

        if ($order === false || $order === null) {
            return false;
        }

        $key = isset($_GET['key']) && function_exists('sanitize_text_field')
            ? sanitize_text_field((string) wp_unslash($_GET['key']))
            : (string) ($_GET['key'] ?? '');

        $orderKey = (method_exists($order, 'get_order_key')) ? (string) $order->get_order_key() : '';

        $keyValid = ($key !== '' && $orderKey !== '' && hash_equals($orderKey, $key));

        if ($context === 'order-received') {
            return $keyValid;
        }

        if ($keyValid) {
            return true; // order-pay with a valid key (guest pay link)
        }

        // order-pay for a logged-in user who owns the order.
        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('get_current_user_id')) {
            $userId = (int) get_current_user_id();

            if ($userId > 0 && method_exists($order, 'get_customer_id')) {
                return (int) $order->get_customer_id() === $userId;
            }
        }

        return false;
    }

    /**
     * Server-side guard: reject checkout submission by guests when the
     * policy is enabled (defense in depth against direct POSTs).
     */
    public function guardCheckoutProcessing(): void {
        if (!$this->isForceLoginEnabled()) {
            return;
        }

        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return;
        }

        if (function_exists('wc_add_notice')) {
            wc_add_notice(
                __('برای تکمیل خرید، ابتدا با شماره موبایل خود وارد حساب کاربری شوید.', 'arma-panel'),
                'error'
            );
        }
    }

    /**
     * Store API (block-based checkout) guard: guests cannot place an order
     * while "force login" is enabled.
     *
     * throwing a RouteException is the canonical way for a
     * plugin to reject a Store API request — WooCommerce turns it into a
     * clean, translatable error response instead of a fatal.
     *
     * @param mixed $order
     * @param mixed $request
     */
    public function guardStoreApiCheckout($order = null, $request = null): void {
        if (!$this->isForceLoginEnabled()) {
            return;
        }

        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return;
        }

        $exceptionClass = 'Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException';

        if (class_exists($exceptionClass)) {
            throw new $exceptionClass(
                'arma-login-required',
                __('برای تکمیل خرید، ابتدا با شماره موبایل خود وارد حساب کاربری شوید.', 'arma-panel'),
                401
            );
        }
    }

    /* -----------------------------------------------------------------
     * 2) Guest cart bridge (capture before login, restore after)
     *
     * the cart is keyed by a RANDOM, single-use token that only
     * ever lives in an HttpOnly cookie on the customer's own browser — never
     * a derivative of the phone number (md5(phone) was guessable and let
     * anyone who knew a phone number hijack/restore someone else's cart).
     * Capture happens strictly AFTER a successful OTP verification and
     * BEFORE the login (see AuthController's staged flow).
     * ----------------------------------------------------------------- */

    /** HttpOnly cookie carrying the guest-cart token. */
    public const GUEST_CART_COOKIE = 'arma_guest_cart';

    /** How long a captured guest cart survives (seconds). */
    private const GUEST_CART_TTL = 3600;

    /**
     * Capture the current guest WooCommerce cart.
     *
     * @return string the random cart token ('' when there was nothing to
     *                capture) — the SAME token is placed in an HttpOnly
     *                cookie on this very response.
     */
    public static function captureGuestCart(): string {
        if (!function_exists('WC') || !class_exists('WC_Cart')) {
            return '';
        }

        try {
            $cart = WC()->cart ?? null;
            $session = WC()->session ?? null;

            if (!$cart || !$session || $cart->is_empty()) {
                return '';
            }

            $payload = [
                'cart' => $cart->get_cart_for_session(),
                'cart_totals' => $session->get('cart_totals'),
                'applied_coupons' => $session->get('applied_coupons'),
                'chosen_shipping_methods' => $session->get('chosen_shipping_methods'),
                'chosen_payment_method' => $session->get('chosen_payment_method'),
            ];

            if (empty($payload['cart'])) {
                return '';
            }

            // Random, unguessable, single-use token — the phone number must
            // never be part of the storage key.
            $token = function_exists('wp_generate_password')
                ? wp_generate_password(32, false)
                : bin2hex(random_bytes(16));

            set_transient(self::GUEST_CART_PREFIX . hash('sha256', $token), $payload, self::GUEST_CART_TTL);

            // HttpOnly: JavaScript can never read/exfiltrate the token.
            if (!headers_sent()) {
                @setcookie(self::GUEST_CART_COOKIE, $token, [
                    'expires'  => time() + self::GUEST_CART_TTL,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure'   => function_exists('is_ssl') && is_ssl(),
                ]);
            }

            return $token;
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * wp_loaded safety net: restore a captured guest cart for an
     * already-logged-in customer who still carries the token cookie.
     *
     * Order of checks is deliberate (cheapest first): the isset() on the
     * cookie means 99.9% of requests return before touching user state.
     */
    public function maybeRestoreGuestCartOnLoad(): void {
        if (!isset($_COOKIE[self::GUEST_CART_COOKIE])) {
            return; // nothing captured — done
        }

        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return; // guests keep their cookie for the upcoming login
        }

        // restoreGuestCart() itself skips the restore when the current cart
        // is not empty, so a customer who already has items keeps them.
        self::restoreGuestCart();
    }

    /**
     * Restore the previously captured guest cart into the freshly logged-in
     * customer session (only when their user cart is empty — never overwrite).
     *
     * The token is read from the HttpOnly cookie set at capture time.
     */
    public static function restoreGuestCart(): bool {
        if (!function_exists('WC') || !class_exists('WC_Cart')) {
            return false;
        }

        $token = isset($_COOKIE[self::GUEST_CART_COOKIE])
            ? (string) $_COOKIE[self::GUEST_CART_COOKIE]
            : '';

        if ($token === '' || strlen($token) > 128) {
            return false;
        }

        $key = self::GUEST_CART_PREFIX . hash('sha256', $token);
        $payload = get_transient($key);

        if (!is_array($payload) || empty($payload['cart'])) {
            self::clearGuestCartCookie();

            return false;
        }

        try {
            if (function_exists('wc_load_cart')) {
                wc_load_cart();
            }

            $cart = WC()->cart ?? null;
            $session = WC()->session ?? null;

            if (!$cart || !$session) {
                return false;
            }

            // Never destroy an existing user cart — merge policy: skip if not empty.
            if (!$cart->is_empty()) {
                delete_transient($key);
                self::clearGuestCartCookie();

                return false;
            }

            $session->set('cart', $payload['cart']);

            if (!empty($payload['cart_totals'])) {
                $session->set('cart_totals', $payload['cart_totals']);
            }
            if (!empty($payload['applied_coupons'])) {
                $session->set('applied_coupons', $payload['applied_coupons']);
            }
            if (!empty($payload['chosen_shipping_methods'])) {
                $session->set('chosen_shipping_methods', $payload['chosen_shipping_methods']);
            }
            if (!empty($payload['chosen_payment_method'])) {
                $session->set('chosen_payment_method', $payload['chosen_payment_method']);
            }

            $cart->get_cart_from_session();

            /*
             * persist the restored cart as the user's WooCommerce
             * persistent cart, so it survives new devices/browsers instead of
             * silently vanishing after the session ends.
             */
            if (class_exists('WC_Persistent_Cart')
                && isset(WC()->persistent_cart)
                && WC()->persistent_cart instanceof \WC_Persistent_Cart
            ) {
                WC()->persistent_cart->update();
            }

            delete_transient($key);
            self::clearGuestCartCookie();

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Drop the guest-cart cookie (after a successful restore or a dead token).
     */
    private static function clearGuestCartCookie(): void {
        if (!headers_sent()) {
            @setcookie(self::GUEST_CART_COOKIE, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'secure'   => function_exists('is_ssl') && is_ssl(),
            ]);
        }
    }

    /* -----------------------------------------------------------------
     * 3) Register incentive banner (Lucky Wheel active, login optional)
     * ----------------------------------------------------------------- */

    public function renderRegisterBannerShortcode(): string {
        return $this->renderRegisterBanner();
    }

    public function renderRegisterBanner(bool $force = false): string {
        if (!$force && !self::shouldShowRegisterBanner($this->settings)) {
            return '';
        }

        if (function_exists('is_admin') && is_admin()) {
            return '';
        }

        $loginUrl = $this->getLoginUrlWithRedirect($this->getCurrentUrl());
        $wheelIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 12l7-3M12 12l-5 6M12 12l-7-3M12 12l5 6M12 12V2"></path></svg>';
        $giftIcon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>';

        // Admin-customizable content (sane defaults kept)
        $bannerText    = (string) $this->settings->get('lucky_wheel.banner_text', '');
        $bannerText    = $bannerText !== '' ? $bannerText : '🎁 با ثبت‌نام در سایت، پس از هر خرید موفق، گردونه شانس و جوایز ویژه به شما تعلق می‌گیرد!';
        $bannerBtnText = (string) $this->settings->get('lucky_wheel.banner_btn_text', '');
        $bannerBtnText = $bannerBtnText !== '' ? $bannerBtnText : 'ورود / ثبت‌نام با موبایل';
        $sanitizeHex   = static fn(string $hex, string $fallback): string => function_exists('sanitize_hex_color')
            ? (sanitize_hex_color($hex) ?: $fallback)
            : (preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : $fallback);
        $color1        = $sanitizeHex((string) $this->settings->get('lucky_wheel.banner_color1', '#0f172a'), '#0f172a');
        $color2        = $sanitizeHex((string) $this->settings->get('lucky_wheel.banner_color2', '#881337'), '#881337');
        $ctaColor      = $sanitizeHex((string) $this->settings->get('lucky_wheel.banner_cta_color', '#fbbf24'), '#fbbf24');
        $ctaTextColor  = $sanitizeHex((string) $this->settings->get('lucky_wheel.banner_cta_text_color', '#451a03'), '#451a03');

        ob_start();
        /*
         * Styles live in assets/css/arma-banner.css (enqueued only while the
         * banner shows). The admin-customizable colors travel as CSS custom
         * properties on the banner root — no inline <style> block.
         */
        ?>
        <div class="arma-wheel-banner arma-wheel-banner--spin" id="arma-wheel-banner" role="region" aria-label="<?php esc_attr_e('بنر گردونه شانس', 'arma-panel'); ?>" style="--arma-wb-bg1:<?php echo esc_html($color1); ?>;--arma-wb-bg2:<?php echo esc_html($color2); ?>;--arma-wb-cta:<?php echo esc_html($ctaColor); ?>;--arma-wb-cta-text:<?php echo esc_html($ctaTextColor); ?>;">
            <span class="arma-wheel-banner__icon"><?php echo $wheelIcon; // phpcs:ignore ?></span>
            <p class="arma-wheel-banner__text">
                <?php echo esc_html($bannerText); ?>
            </p>
            <a href="<?php echo esc_url($loginUrl); ?>" class="arma-wheel-banner__cta">
                <?php echo $giftIcon; // phpcs:ignore ?>
                <span><?php echo esc_html($bannerBtnText); ?></span>
            </a>
            <button type="button" class="arma-wheel-banner__close" id="arma-wheel-banner-close" aria-label="<?php esc_attr_e('بستن', 'arma-panel'); ?>">&times;</button>
        </div>
        <script>
        (function () {
            try {
                if (sessionStorage.getItem('armaWheelBannerClosed') === '1') {
                    var b = document.getElementById('arma-wheel-banner');
                    if (b) { b.style.display = 'none'; }
                    return;
                }
                var btn = document.getElementById('arma-wheel-banner-close');
                if (btn) {
                    btn.addEventListener('click', function () {
                        sessionStorage.setItem('armaWheelBannerClosed', '1');
                        var b = document.getElementById('arma-wheel-banner');
                        if (b) { b.remove(); }
                    });
                }
            } catch (e) { /* storage unavailable — ignore */ }
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    /* -----------------------------------------------------------------
     * URL helpers
     * ----------------------------------------------------------------- */

    /**
     * OTP login URL carrying a validated "return to" target.
     */
    public function getLoginUrlWithRedirect(string $redirectTo): string {
        $loginUrl = function_exists('arma_panel_url') ? arma_panel_url('login') : home_url('/arma-panel/login/');

        if ($redirectTo === '' || $redirectTo === '/') {
            return $loginUrl;
        }

        return add_query_arg('redirect_to', rawurlencode($redirectTo), $loginUrl);
    }

    /**
     * Current full request URL (safe to embed in a redirect target).
     */
    public function getCurrentUrl(): string {
        $scheme = (function_exists('is_ssl') && is_ssl()) ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip characters that could enable header/HTML injection.
        $url = str_replace(['"', "'", '<', '>', "\0", "\n", "\r"], '', $scheme . '://' . $host . $uri);

        return $url;
    }
}
