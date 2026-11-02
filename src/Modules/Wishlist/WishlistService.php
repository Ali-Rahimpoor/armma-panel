<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Wishlist;

use Arma\ArmaPanel\Core\SettingsManager;

/**
 * Wishlist (favorites) service — per-user product list stored in user meta.
 *
 * Storage: user meta `_arma_wishlist` = ordered list of product IDs
 * (newest last). Everything is gated by the `wishlist` module toggle.
 */
class WishlistService {

    public const META_KEY = '_arma_wishlist';
    private const MAX_ITEMS = 200;

    private SettingsManager $settings;

    public function __construct(SettingsManager $settings) {
        $this->settings = $settings;
    }

    public function isEnabled(): bool {
        return $this->settings->isModuleEnabled('wishlist');
    }

    /* -----------------------------------------------------------------
     * List storage
     * ----------------------------------------------------------------- */

    /**
     * @return array<int, int>
     */
    public function getProductIds(int $userId): array {
        if ($userId <= 0) {
            return [];
        }

        $raw = get_user_meta($userId, self::META_KEY, true);
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function isFavorite(int $userId, int $productId): bool {
        return $productId > 0 && in_array($productId, $this->getProductIds($userId), true);
    }

    /**
     * Toggle a product for a user.
     *
     * @return bool true when the product was ADDED, false when removed.
     */
    public function toggleProduct(int $userId, int $productId): bool {
        if ($userId <= 0 || $productId <= 0) {
            return false;
        }

        $ids = $this->getProductIds($userId);

        if (in_array($productId, $ids, true)) {
            $ids = array_values(array_diff($ids, [$productId]));
            update_user_meta($userId, self::META_KEY, $ids);

            return false;
        }

        $ids[] = $productId;

        if (count($ids) > self::MAX_ITEMS) {
            $ids = array_slice($ids, -self::MAX_ITEMS); // keep the newest
        }

        update_user_meta($userId, self::META_KEY, $ids);

        return true;
    }

    public function removeProduct(int $userId, int $productId): bool {
        if ($userId <= 0 || $productId <= 0) {
            return false;
        }

        $ids = $this->getProductIds($userId);
        if (!in_array($productId, $ids, true)) {
            return false;
        }

        update_user_meta($userId, self::META_KEY, array_values(array_diff($ids, [$productId])));

        return true;
    }

    /* -----------------------------------------------------------------
     * Panel page data (product cards)
     * ----------------------------------------------------------------- */

    /**
     * Build the wishlist page cards (newest first). Products that no longer
     * exist are skipped and pruned from the stored list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWishlistData(int $userId): array {
        $ids      = $this->getProductIds($userId);
        $products = [];
        $keep     = [];

        foreach (array_reverse($ids) as $id) {
            $product = $this->getProduct($id);
            if ($product === null) {
                continue; // deleted product: do not render, do not keep
            }

            $keep[]     = $id;
            $products[] = [
                'id'             => $id,
                'title'          => $this->productName($product),
                'price_html'     => $this->productPrice($product),
                'permalink'      => $this->productPermalink($id, $product),
                'image'          => $this->productImage($id),
                'add_to_cart_url'=> $this->productAddToCartUrl($id, $product),
                'in_stock'       => $this->productInStock($product),
            ];
        }

        // Prune deleted products from storage (keep the list honest)
        if (count($keep) !== count($ids)) {
            update_user_meta($userId, self::META_KEY, array_values(array_reverse($keep)));
        }

        return $products;
    }

    /**
     * @return object|null WC_Product-like object or null
     */
    private function getProduct(int $id) {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($id);

        return ($product !== null && $product !== false) ? $product : null;
    }

    private function productName(object $product): string {
        if (method_exists($product, 'get_name')) {
            return (string) $product->get_name();
        }

        return '';
    }

    private function productPrice(object $product): string {
        if (method_exists($product, 'get_price_html')) {
            $price = $product->get_price_html();

            return is_string($price) ? $price : '';
        }

        return '';
    }

    private function productPermalink(int $id, object $product): string {
        if (method_exists($product, 'get_permalink')) {
            $permalink = $product->get_permalink();

            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        if (function_exists('get_permalink')) {
            $permalink = get_permalink($id);

            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        return '#';
    }

    private function productImage(int $id): string {
        if (function_exists('get_the_post_thumbnail_url')) {
            $url = get_the_post_thumbnail_url($id, 'woocommerce_thumbnail');

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function productAddToCartUrl(int $id, object $product): string {
        if (method_exists($product, 'add_to_cart_url')) {
            $url = $product->add_to_cart_url();

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function productInStock(object $product): bool {
        if (method_exists($product, 'is_in_stock')) {
            return (bool) $product->is_in_stock();
        }

        return true;
    }

    /* -----------------------------------------------------------------
     * Admin-configurable presentation settings
     * ----------------------------------------------------------------- */

    /** @return string 'after_cart' | 'before_cart' | 'after_title' | 'after_price' | 'shortcode' */
    public function getButtonPosition(): string {
        $pos = (string) $this->settings->get('wishlist.button_position', 'after_cart');

        return in_array($pos, ['after_cart', 'before_cart', 'after_title', 'after_price', 'shortcode'], true) ? $pos : 'after_cart';
    }

    /** @return string 'button' (full, with text) | 'icon' (small heart only) */
    public function getButtonStyle(): string {
        $style = (string) $this->settings->get('wishlist.button_style', 'button');

        return $style === 'icon' ? 'icon' : 'button';
    }

    public function getButtonText(): string {
        $text = trim((string) $this->settings->get('wishlist.button_text', ''));

        return $text !== '' ? $text : 'افزودن به علاقه‌مندی‌ها';
    }

    public function getAddedText(): string {
        return 'در علاقه‌مندی‌ها ✓';
    }

    public function getHeartColor(): string {
        $color = trim((string) $this->settings->get('wishlist.heart_color', ''));

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '#e11d48';
    }

    /** @return string 'login' (send to OTP login) | 'hide' (no button) */
    public function getGuestBehavior(): string {
        return ((string) $this->settings->get('wishlist.guest_behavior', 'login')) === 'hide' ? 'hide' : 'login';
    }

    public function getPageTitle(): string {
        $title = trim((string) $this->settings->get('wishlist.page_title', ''));

        return $title !== '' ? $title : 'علاقه‌مندی‌های من';
    }

    public function getColumns(): int {
        $cols = (int) $this->settings->get('wishlist.columns', 3);

        return ($cols >= 2 && $cols <= 4) ? $cols : 3;
    }

    public function showPrice(): bool {
        return (bool) $this->settings->get('wishlist.show_price', true);
    }

    public function showAddToCart(): bool {
        return (bool) $this->settings->get('wishlist.show_add_to_cart', true);
    }
}
