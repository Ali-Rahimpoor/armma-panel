<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Wishlist;

use Arma\ArmaPanel\Core\SettingsManager;
use Throwable;
use WC_Product;

/**
 * Wishlist controller — the product-page heart button/icon and its AJAX
 * toggle, the [arma-wishlist] shortcode for manual placement (Elementor
 * etc.) and the guest behavior (OTP login redirect or hidden).
 *
 * The button is fully self-contained (inline style + script printed with
 * the button) so it works on any theme without loading extra assets.
 *
 * Placement:
 *   • automatic — one of the 4 hook positions, or "shortcode" = manual only
 *   • manual    — [arma-wishlist] / [arma-wishlist style="icon"] /
 *                 [arma-wishlist id="123"]
 *   A product never renders twice on the same page (first placement wins),
 *   so automatic + manual placement never duplicate each other.
 */
class WishlistController {

    private WishlistService $service;
    private SettingsManager $settings;

    /** @var bool styles/scripts already printed on this page */
    private static $assetsPrinted = false;

    /** @var array<int, int> product ids already rendered on this request */
    private static $renderedProducts = [];

    public function __construct(WishlistService $service, SettingsManager $settings) {
        $this->service  = $service;
        $this->settings = $settings;
    }

    public function registerHooks(): void {
        if (!$this->service->isEnabled()) {
            return;
        }

        add_action('wp_ajax_arma_toggle_wishlist', [$this, 'handleToggle']);
        add_action('wp_ajax_nopriv_arma_toggle_wishlist', [$this, 'handleToggle']);

        // No-JS / broken-JS fallback: the panel remove control is a real link
        // (?arma_remove_wishlist=ID&_arma_wishlist_nonce=...) — handled here
        // with a post/redirect/get so it works without any JavaScript.
        add_action('template_redirect', [$this, 'maybeHandleRemoveGet']);

        // Manual placement — works inside Elementor / any content area.
        // Three spellings on purpose: the canonical tag plus the two
        // variants people commonly type by mistake.
        if (function_exists('add_shortcode')) {
            add_shortcode('arma-wishlist', [$this, 'handleShortcode']);
            add_shortcode('arma-whwishlist', [$this, 'handleShortcode']); // double-h typo variant
            add_shortcode('arma-whishlist', [$this, 'handleShortcode']);  // alt spelling (whishlist)
            add_shortcode('arma_wishlist', [$this, 'handleShortcode']);  // underscore variant
        }

        // Automatic placement on the single-product page (admin setting).
        // NOTE: product pages rebuilt with a page builder (e.g. Elementor
        // Theme Builder) never fire woocommerce_single_product_summary,
        // so the "below title" / "below price" positions cannot appear
        // there — the shortcode above is the reliable manual way.
        $position = $this->service->getButtonPosition();
        switch ($position) {
            case 'shortcode': // manual only — no automatic output
                break;

            case 'before_cart':
                add_action('woocommerce_before_add_to_cart_button', [$this, 'renderHeartButton'], 20);
                break;

            case 'after_title':
                add_action('woocommerce_single_product_summary', [$this, 'renderHeartButton'], 7);
                break;

            case 'after_price':
                add_action('woocommerce_single_product_summary', [$this, 'renderHeartButton'], 11);
                break;

            case 'after_cart':
            default:
                add_action('woocommerce_after_add_to_cart_button', [$this, 'renderHeartButton'], 20);
                break;
        }
    }

    /* -----------------------------------------------------------------
     * AJAX: toggle a product in the current user's wishlist
     * ----------------------------------------------------------------- */

    public function handleToggle(): void {
        try {
            if (!$this->service->isEnabled()) {
                wp_send_json_error(['message' => __('ماژول علاقه‌مندی‌ها غیرفعال است.', 'arma-panel')]);
            }

            check_ajax_referer('arma_wishlist_nonce', 'nonce');

            $userId = function_exists('get_current_user_id') ? get_current_user_id() : 0;

            if ($userId <= 0) {
                if ($this->service->getGuestBehavior() === 'hide') {
                    wp_send_json_error(['message' => __('برای افزودن به علاقه‌مندی‌ها ابتدا وارد حساب کاربری شوید.', 'arma-panel')]);
                }

                wp_send_json_error([
                    'message'  => __('برای افزودن به علاقه‌مندی‌ها ابتدا وارد حساب کاربری شوید.', 'arma-panel'),
                    'redirect' => $this->getLoginUrl(),
                ]);
            }

            $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
            if ($productId <= 0) {
                wp_send_json_error(['message' => __('محصول نامعتبر است.', 'arma-panel')]);
            }

            $added = $this->service->toggleProduct($userId, $productId);

            wp_send_json_success([
                'added'    => $added,
                'product_id' => $productId,
                'count'    => count($this->service->getProductIds($userId)),
                'message'  => $added
                    ? __('به علاقه‌مندی‌ها اضافه شد.', 'arma-panel')
                    : __('از علاقه‌مندی‌ها حذف شد.', 'arma-panel'),
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => __('خطای پیش‌بینی نشده در علاقه‌مندی‌ها.', 'arma-panel')]);
        }
    }

    /**
     * GET fallback for the panel remove links (template_redirect).
     *
     * Removes the product and redirects back to the wishlist page (PRG), so
     * removal works even when JavaScript never runs (optimizers, old themes,
     * broken head scripts). Silently no-ops on any invalid input.
     */
    public function maybeHandleRemoveGet(): void {
        if (!isset($_GET['arma_remove_wishlist'])) {
            return;
        }

        if (!$this->service->isEnabled()) {
            return;
        }

        $productId = isset($_GET['arma_remove_wishlist']) ? (int) $_GET['arma_remove_wishlist'] : 0;
        $nonce     = isset($_GET['_arma_wishlist_nonce']) ? sanitize_text_field(wp_unslash($_GET['_arma_wishlist_nonce'])) : '';
        $userId    = function_exists('get_current_user_id') ? get_current_user_id() : 0;

        if ($productId <= 0 || $userId <= 0) {
            return;
        }

        if (!function_exists('wp_verify_nonce') || !wp_verify_nonce($nonce, 'arma_wishlist_remove')) {
            return;
        }

        $this->service->removeProduct($userId, $productId);

        $url = function_exists('arma_panel_url') ? arma_panel_url('wishlist') : '';
        if ($url === '' && function_exists('home_url')) {
            $url = home_url('/');
        }

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
            exit;
        }

        header('Location: ' . $url, true, 302);
        exit;
    }

    /* -----------------------------------------------------------------
     * Shortcode: [arma-wishlist]
     *
     * Atts:
     *   style="button|icon"  — override the admin style per placement
     *   id="123"             — product id (defaults to the current product)
     * ----------------------------------------------------------------- */

    public function handleShortcode($atts = []): string {
        $atts = is_array($atts) ? $atts : [];

        $style = strtolower(trim((string) ($atts['style'] ?? '')));
        if ($style !== 'button' && $style !== 'icon') {
            $style = $this->service->getButtonStyle();
        }

        $productId = isset($atts['id']) ? (int) $atts['id'] : 0;
        if ($productId <= 0) {
            $productId = $this->resolveProductId();
        }

        return $this->renderButtonHtml($productId, $style);
    }

    /* -----------------------------------------------------------------
     * Product-page heart button (automatic placements)
     * ----------------------------------------------------------------- */

    public function renderHeartButton(): void {
        echo $this->renderButtonHtml($this->resolveProductId(), $this->service->getButtonStyle());
    }

    /**
     * Build the heart button/icon HTML.
     *
     * Renders at most ONE button per product per request, so the automatic
     * position and the shortcode never duplicate each other. Returns ''
     * when the module is off, the product is unknown, or the user is a
     * guest configured to be hidden.
     */
    public function renderButtonHtml(int $productId, string $style): string {
        if (!$this->service->isEnabled() || $productId <= 0) {
            return '';
        }

        if (in_array($productId, self::$renderedProducts, true)) {
            return ''; // already placed on this page — first placement wins
        }

        $userId  = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        $isGuest = $userId <= 0;

        // Guests with the "hide" behavior never see the button
        if ($isGuest && $this->service->getGuestBehavior() === 'hide') {
            return '';
        }

        self::$renderedProducts[] = $productId;

        $isFavorite = $isGuest ? false : $this->service->isFavorite($userId, $productId);
        $addText    = $this->service->getButtonText();
        $addedText  = $this->service->getAddedText();
        $heartColor = $this->service->getHeartColor();
        $isIcon     = ($style === 'icon');
        $labelText  = $isFavorite ? $addedText : $addText;

        $class = 'arma-wishlist-btn'
            . ($isIcon ? ' arma-wishlist-btn--icon' : '')
            . ($isFavorite ? ' is-active' : '');

        $html  = $this->printAssetsOnce();
        $html .= '<button type="button"'
            . ' class="' . esc_attr($class) . '"'
            . ' data-product-id="' . esc_attr((string) $productId) . '"'
            . ' data-add-text="' . esc_attr($addText) . '"'
            . ' data-added-text="' . esc_attr($addedText) . '"'
            . ' data-style="' . esc_attr($isIcon ? 'icon' : 'button') . '"'
            . ($isGuest
                ? ' data-guest="1" data-login-url="' . esc_url($this->getLoginUrl()) . '"'
                : '')
            . ' style="--arma-heart: ' . esc_attr($heartColor) . ';"'
            . ($isIcon
                ? ' title="' . esc_attr($labelText) . '"'
                    . ' aria-label="' . esc_attr($labelText) . '"'
                : '')
            . ' aria-pressed="' . ($isFavorite ? 'true' : 'false') . '">';

        $html .= '<svg class="arma-wishlist-btn__heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>'
            . '</svg>';

        if (!$isIcon) {
            $html .= '<span class="arma-wishlist-btn__label">' . esc_html($labelText) . '</span>';
        }

        $html .= '</button>';

        return $html;
    }

    /**
     * Resolve the current product id from the global $product or the post id.
     */
    private function resolveProductId(): int {
        global $product;

        if ($product instanceof WC_Product) {
            $id = (int) $product->get_id();

            if ($id > 0) {
                return $id;
            }
        }

        if ($product !== null && method_exists($product, 'get_id')) {
            $id = (int) $product->get_id();

            if ($id > 0) {
                return $id;
            }
        }

        return function_exists('get_the_ID') ? (int) get_the_ID() : 0;
    }

    /**
     * The OTP login page: the customer panel itself renders the login form
     * for guests, so guests are sent to the panel home.
     */
    private function getLoginUrl(): string {
        if (function_exists('arma_panel_url')) {
            $url = arma_panel_url('dashboard');

            if ($url !== '') {
                return $url;
            }
        }

        return function_exists('home_url')
            ? home_url('/')
            : '/';
    }

    /**
     * Self-contained style + script, returned once per page (bundled with
     * the first rendered button).
     */
    private function printAssetsOnce(): string {
        if (self::$assetsPrinted) {
            return '';
        }
        self::$assetsPrinted = true;

        $ajaxUrl = function_exists('admin_url')
            ? (string) admin_url('admin-ajax.php')
            : '/wp-admin/admin-ajax.php';
        $nonce = function_exists('wp_create_nonce')
            ? (string) wp_create_nonce('arma_wishlist_nonce')
            : '';

        /*
         * v1.10.3 — premium button skin. Neutral card base with the admin's
         * heart color as the accent: outline heart on a white pill, solid
         * color + glow + sheen when active, spring "pop" on toggle.
         */
        $html  = "<style>\n"
            . ".arma-wishlist-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;gap:9px;margin:12px 6px 0 0;padding:12px 24px;border:1.5px solid #e6e8ef;border-radius:999px;background:#fff;color:var(--arma-heart,#e11d48);font:inherit;font-size:0.88rem;font-weight:700;line-height:1;cursor:pointer;user-select:none;-webkit-tap-highlight-color:transparent;box-shadow:0 1px 2px rgba(15,23,42,.06),0 4px 12px -6px rgba(15,23,42,.12);transition:transform .2s cubic-bezier(.34,1.56,.64,1),box-shadow .2s ease,border-color .2s ease,background .2s ease,color .2s ease;}\n"
            . ".arma-wishlist-btn:hover{transform:translateY(-2px);border-color:var(--arma-heart,#e11d48);box-shadow:0 10px 24px -8px rgba(15,23,42,.22),0 6px 14px -8px var(--arma-heart,#e11d48);}\n"
            . ".arma-wishlist-btn:active{transform:translateY(0) scale(.96);}\n"
            . ".arma-wishlist-btn:focus-visible{outline:2px solid var(--arma-heart,#e11d48);outline-offset:3px;}\n"
            . ".arma-wishlist-btn__heart{width:18px;height:18px;flex-shrink:0;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:fill .2s ease,transform .2s ease;}\n"
            . ".arma-wishlist-btn.is-active{border-color:var(--arma-heart,#e11d48);background:var(--arma-heart,#e11d48);color:#fff;box-shadow:0 10px 26px -8px var(--arma-heart,#e11d48),inset 0 1px 0 rgba(255,255,255,.28);}\n"
            . ".arma-wishlist-btn.is-active:hover{box-shadow:0 14px 30px -8px var(--arma-heart,#e11d48),inset 0 1px 0 rgba(255,255,255,.28);}\n"
            . ".arma-wishlist-btn.is-active .arma-wishlist-btn__heart{fill:currentColor;}\n"
            . ".arma-wishlist-btn.is-busy{opacity:.65;pointer-events:none;}\n"
            . ".arma-wishlist-btn.is-busy .arma-wishlist-btn__heart{animation:arma-heart-busy 1s ease-in-out infinite;}\n"
            . ".arma-wishlist-btn--icon{width:46px;height:46px;padding:0;border-radius:50%;}\n"
            . ".arma-wishlist-btn--icon .arma-wishlist-btn__heart{width:21px;height:21px;}\n"
            . ".arma-wishlist-btn--icon:hover .arma-wishlist-btn__heart{transform:scale(1.15);}\n"
            . ".arma-wishlist-btn--icon.is-active{box-shadow:0 10px 22px -6px var(--arma-heart,#e11d48),inset 0 1px 0 rgba(255,255,255,.3);}\n"
            // Stretch-proofing (public 1.0.0): themes routinely force width:100% on
            // every button inside the add-to-cart form (that is why the cart
            // button is a wide bar). Without hard locks the little heart icon
            // inherited that stretch and distorted into a wide pill. These
            // !important guards keep the icon a perfect circle next to the
            // cart button on ANY theme; the full button keeps its natural
            // content width instead of being blown up like the cart bar.
            . ".arma-wishlist-btn{width:auto !important;max-width:100% !important;height:auto !important;flex:0 0 auto !important;align-self:center !important;box-sizing:border-box !important;padding:12px 24px !important;border-radius:999px !important;}\n"
            . ".arma-wishlist-btn__heart{width:18px !important;height:18px !important;max-width:18px !important;max-height:18px !important;flex:0 0 auto !important;}\n"
            . ".arma-wishlist-btn--icon{width:46px !important;min-width:46px !important;max-width:46px !important;height:46px !important;min-height:46px !important;max-height:46px !important;aspect-ratio:1/1 !important;padding:0 !important;border-radius:50% !important;}\n"
            . ".arma-wishlist-btn--icon .arma-wishlist-btn__heart{width:21px !important;height:21px !important;max-width:21px !important;max-height:21px !important;}\n"
            . "@keyframes arma-heart-pop{0%{transform:scale(1);}45%{transform:scale(1.4);}75%{transform:scale(.9);}100%{transform:scale(1);}}\n"
            . "@keyframes arma-heart-busy{0%,100%{opacity:1;}50%{opacity:.35;}}\n"
            . ".arma-wishlist-btn__heart.is-just-toggled{animation:arma-heart-pop .5s cubic-bezier(.34,1.56,.64,1);}\n"
            . "@media (prefers-reduced-motion: reduce){.arma-wishlist-btn{transition:none !important;}.arma-wishlist-btn__heart{transition:none !important;animation:none !important;}}\n"
            . "</style>\n<script>\n"
            . "window.armaWishlistData = {\n"
            . "    ajax_url: " . wp_json_encode($ajaxUrl) . ",\n"
            . "    nonce: " . wp_json_encode($nonce) . "\n"
            . "};\n"
            . "(function () {\n"
            . "    if (window.armaWishlistInit) { return; }\n"
            . "    window.armaWishlistInit = true;\n"
            . "    document.addEventListener('click', function (e) {\n"
            . "        var btn = e.target.closest ? e.target.closest('.arma-wishlist-btn') : null;\n"
            . "        if (!btn) { return; }\n"
            . "        e.preventDefault();\n"
            . "        if (btn.getAttribute('data-guest') === '1') {\n"
            . "            window.location.href = btn.getAttribute('data-login-url') || '/';\n"
            . "            return;\n"
            . "        }\n"
            . "        if (!window.armaWishlistData || !window.armaWishlistData.nonce) { return; }\n"
            . "        var fd = new FormData();\n"
            . "        fd.append('action', 'arma_toggle_wishlist');\n"
            . "        fd.append('nonce', window.armaWishlistData.nonce);\n"
            . "        fd.append('product_id', btn.getAttribute('data-product-id'));\n"
            . "        btn.classList.add('is-busy');\n"
            . "        fetch(window.armaWishlistData.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })\n"
            . "            .then(function (r) { return r.json(); })\n"
            . "            .then(function (res) {\n"
            . "                btn.classList.remove('is-busy');\n"
            . "                if (res && res.success) {\n"
            . "                    var added = !!res.data.added;\n"
            . "                    var newText = added ? btn.getAttribute('data-added-text') : btn.getAttribute('data-add-text');\n"
            . "                    btn.classList.toggle('is-active', added);\n"
            . "                    btn.setAttribute('aria-pressed', added ? 'true' : 'false');\n"
            . "                    btn.setAttribute('title', newText);\n"
            . "                    btn.setAttribute('aria-label', newText);\n"
            . "                    var label = btn.querySelector('.arma-wishlist-btn__label');\n"
            . "                    if (label) { label.textContent = newText; }\n"
            . "                    var heart = btn.querySelector('.arma-wishlist-btn__heart');\n"
            . "                    if (heart) {\n"
            . "                        heart.classList.remove('is-just-toggled');\n"
            . "                        void heart.offsetWidth; /* restart the pop animation */\n"
            . "                        heart.classList.add('is-just-toggled');\n"
            . "                    }\n"
            . "                } else if (res && res.data && res.data.redirect) {\n"
            . "                    window.location.href = res.data.redirect;\n"
            . "                } else if (res && res.data && res.data.message) {\n"
            . "                    window.alert(res.data.message);\n"
            . "                }\n"
            . "            })\n"
            . "            .catch(function () { btn.classList.remove('is-busy'); });\n"
            . "    });\n"
            . "})();\n"
            . "</script>\n";

        return $html;
    }
}
