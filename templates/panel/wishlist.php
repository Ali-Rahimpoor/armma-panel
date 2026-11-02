<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer panel — wishlist (favorites) page.
 *
 * @var array<string, mixed> $user
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var array<string, mixed> $section_data
 */

$products   = $section_data['products'] ?? [];
$count      = (int) ($section_data['count'] ?? count($products));

$wishlist = new \Arma\ArmaPanel\Modules\Wishlist\WishlistService($settings);
$pageTitle    = $wishlist->getPageTitle();
$columns      = $wishlist->getColumns();
$showPrice    = $wishlist->showPrice();
$showCart     = $wishlist->showAddToCart();

$shopUrl = function_exists('wc_get_page_permalink')
    ? (string) wc_get_page_permalink('shop')
    : (function_exists('home_url') ? home_url('/') : '/');
if ($shopUrl === '') {
    $shopUrl = function_exists('home_url') ? home_url('/') : '/';
}

/*
 * v1.10.5: the remove control is a REAL link (GET fallback) so removal works
 * even when JavaScript is broken by a theme/optimizer — the JS layer below
 * intercepts the click and upgrades it to AJAX, falling back to the link
 * itself whenever AJAX is unavailable.
 */
$removeNonce = function_exists('wp_create_nonce') ? wp_create_nonce('arma_wishlist_remove') : '';
$panelWishlistUrl = function_exists('arma_panel_url') ? arma_panel_url('wishlist') : '';
if ($panelWishlistUrl === '' && function_exists('home_url')) {
    $panelWishlistUrl = home_url('/');
}
$removeUrlBase = $panelWishlistUrl . (strpos($panelWishlistUrl, '?') === false ? '?' : '&');
?>

<!-- v1.10.5: self-contained remove-button styles (!important guards against
     theme/optimizer interference — reports of an invisible/no-icon button on
     live themes came from external CSS winning the cascade) -->
<style>
.arma-wishlist-card__remove{position:absolute;top:10px;left:10px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:none !important;border-radius:50% !important;background:var(--arma-danger,#ef4444) !important;color:#fff !important;box-shadow:0 4px 14px -2px rgba(239,68,68,.5);z-index:2;text-decoration:none !important;cursor:pointer;transition:transform .16s ease,background .16s ease;}
.arma-wishlist-card__remove svg{width:16px;height:16px;display:block;}
.arma-wishlist-card__remove:hover{background:#dc2626 !important;transform:scale(1.08);}
.arma-wishlist-card__remove:active{transform:scale(.94);}
.arma-wishlist-card__remove:focus-visible{outline:2px solid #fff;outline-offset:2px;}
</style>

<div class="arma-card-header" style="padding: 20px 20px 0;">
    <h2 class="arma-card-header__title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
        </svg>
        <span><?php echo esc_html($pageTitle); ?></span>
        <?php if ($count > 0): ?>
            <span class="arma-nav__badge" style="margin-right: 6px;"><?php echo esc_html(arma_to_persian_digits((string) $count)); ?></span>
        <?php endif; ?>
    </h2>
</div>

<div class="arma-card" style="border: none; box-shadow: none; background: transparent;">
    <div class="arma-card-body" style="padding: 16px 4px 0;">
        <?php if (empty($products)): ?>
            <!-- Simple empty state (design system) -->
            <div class="arma-empty-state">
                <p class="arma-empty-state__text"><?php esc_html_e('هنوز محصولی به علاقه‌مندی‌های خود اضافه نکرده‌اید.', 'arma-panel'); ?></p>
                <a href="<?php echo esc_url($shopUrl); ?>" class="arma-btn arma-btn--primary arma-btn--sm">
                    <?php esc_html_e('مشاهده فروشگاه و انتخاب محصول', 'arma-panel'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="arma-wishlist-grid arma-wishlist-grid--c<?php echo (int) $columns; ?>">
                <?php foreach ($products as $product): ?>
                    <div class="arma-wishlist-card" data-product-id="<?php echo esc_attr((string) $product['id']); ?>">
                        <a href="<?php echo esc_url($removeUrlBase . 'arma_remove_wishlist=' . (int) $product['id'] . '&_arma_wishlist_nonce=' . rawurlencode($removeNonce)); ?>"
                           class="arma-wishlist-card__remove"
                           data-product-id="<?php echo esc_attr((string) $product['id']); ?>"
                           title="<?php esc_attr_e('حذف از علاقه‌مندی‌ها', 'arma-panel'); ?>"
                           aria-label="<?php esc_attr_e('حذف از علاقه‌مندی‌ها', 'arma-panel'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </a>

                        <a href="<?php echo esc_url((string) $product['permalink']); ?>" class="arma-wishlist-card__link">
                            <div class="arma-wishlist-card__thumb">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?php echo esc_url((string) $product['image']); ?>" alt="<?php echo esc_attr((string) $product['title']); ?>" loading="lazy">
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <span class="arma-wishlist-card__title"><?php echo esc_html((string) $product['title']); ?></span>
                        </a>

                        <?php if ($showPrice && !empty($product['price_html'])): ?>
                            <span class="arma-wishlist-card__price"><?php echo wp_kses_post((string) $product['price_html']); ?></span>
                        <?php endif; ?>

                        <?php if (!empty($product['in_stock'])): ?>
                            <span class="arma-status-pill" style="color: var(--arma-success); background-color: var(--arma-success-bg, #ecfdf5);"><?php esc_html_e('موجود', 'arma-panel'); ?></span>
                        <?php else: ?>
                            <span class="arma-status-pill" style="color: var(--arma-danger); background-color: var(--arma-danger-bg, #fef2f2);"><?php esc_html_e('ناموجود', 'arma-panel'); ?></span>
                        <?php endif; ?>

                        <?php if ($showCart && !empty($product['add_to_cart_url'])): ?>
                            <a href="<?php echo esc_url((string) $product['add_to_cart_url']); ?>" class="arma-btn arma-btn--primary arma-btn--sm arma-wishlist-card__cart">
                                <?php esc_html_e('افزودن به سبد خرید', 'arma-panel'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <script>
            (function () {
                // No armaPanelData (broken/optimized-away head script)? Do nothing —
                // the remove links still work as plain navigations (GET fallback).
                if (!window.armaPanelData || !window.armaPanelData.wishlist_nonce) { return; }

                document.addEventListener('click', function (e) {
                    var link = e.target.closest ? e.target.closest('.arma-wishlist-card__remove') : null;
                    if (!link) { return; }
                    e.preventDefault();
                    var card = link.closest('.arma-wishlist-card');
                    if (!card || card.classList.contains('is-busy')) { return; }
                    card.classList.add('is-busy');
                    var fd = new FormData();
                    fd.append('action', 'arma_toggle_wishlist');
                    fd.append('nonce', window.armaPanelData.wishlist_nonce);
                    fd.append('product_id', card.getAttribute('data-product-id'));
                    fetch(window.armaPanelData.ajax_url, { method: 'POST', credentials: 'same-origin', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res && res.success) {
                                card.style.transition = 'opacity .18s ease, transform .18s ease';
                                card.style.opacity = '0';
                                card.style.transform = 'scale(.92)';
                                setTimeout(function () {
                                    card.remove();
                                    if (!document.querySelector('.arma-wishlist-card')) {
                                        window.location.reload();
                                    }
                                }, 190);
                            } else {
                                // AJAX refused — use the server-side link fallback
                                window.location.href = link.getAttribute('href');
                            }
                        })
                        .catch(function () {
                            // network/JS failure — the link still removes server-side
                            window.location.href = link.getAttribute('href');
                        });
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>
