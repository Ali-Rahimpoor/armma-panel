<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var array<string, mixed> $user
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var array<string, mixed> $section_data
 */

$isSingle = (bool) ($section_data['is_single'] ?? false);
$ordersBaseUrl = function_exists('arma_panel_url') ? arma_panel_url('orders') : home_url('/' . trim((string)$settings->get('general.panel_slug', 'arma-panel'), '/') . '/orders/');
?>

<?php if ($isSingle): ?>
    <?php
    $order = $section_data['order'] ?? null;
    if (!$order):
    ?>
        <div class="arma-card">
            <div class="arma-empty">
                <p><?php esc_html_e('سفارش مورد نظر یافت نشد.', 'arma-panel'); ?></p>
                <a href="<?php echo esc_url($ordersBaseUrl); ?>" class="arma-btn arma-btn--outline">
                    <?php esc_html_e('بازگشت به لیست سفارش‌ها', 'arma-panel'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="arma-card">
            <div class="arma-card-header">
                <h2 class="arma-card-header__title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    </svg>
                    <span><?php printf(esc_html__('جزئیات سفارش #%s', 'arma-panel'), esc_html($order['order_number'])); ?></span>
                </h2>
                <a href="<?php echo esc_url($ordersBaseUrl); ?>" class="arma-btn arma-btn--outline arma-btn--sm">
                    <?php esc_html_e('بازگشت به لیست', 'arma-panel'); ?>
                </a>
            </div>

            <div style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 16px; background: #f8fafc; border-radius: var(--arma-radius-sm); border: 1px solid var(--arma-border);">
                    <div>
                        <span style="font-size: 0.85rem; color: var(--arma-text-muted);"><?php esc_html_e('تاریخ ثبت:', 'arma-panel'); ?></span>
                        <strong><?php echo esc_html($order['date_persian']); ?></strong>
                    </div>
                    <div>
                        <span style="font-size: 0.85rem; color: var(--arma-text-muted);"><?php esc_html_e('وضعیت:', 'arma-panel'); ?></span>
                        <span class="arma-badge" style="color: <?php echo esc_attr($order['status_color']); ?>; background-color: <?php echo esc_attr($order['status_bg']); ?>;">
                            <?php echo esc_html($order['status_label']); ?>
                        </span>
                    </div>
                    <div>
                        <span style="font-size: 0.85rem; color: var(--arma-text-muted);"><?php esc_html_e('مبلغ کل:', 'arma-panel'); ?></span>
                        <strong style="color: var(--arma-primary);"><?php echo esc_html($order['total_formatted']); ?></strong>
                    </div>
                </div>

                <?php if (!empty($order['tracking_code'])): ?>
                    <!-- Shipment tracking info (set by the admin on the order page) -->
                    <div style="margin-top: 16px; padding: 14px 16px; border: 1px dashed var(--arma-success); border-radius: var(--arma-radius-md); background: var(--arma-success-bg, #ecfdf5); display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--arma-success)" stroke-width="2" style="flex-shrink: 0;"><rect x="1" y="3" width="15" height="13"></rect><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                        <div style="flex: 1; min-width: 200px;">
                            <strong style="color: var(--arma-heading); font-size: 0.9rem;"><?php esc_html_e('سفارش شما ارسال شد', 'arma-panel'); ?></strong>
                            <div style="font-size: 0.82rem; color: var(--arma-text); margin-top: 4px;">
                                <?php if (!empty($order['tracking_method'])): ?>
                                    <?php esc_html_e('روش حمل:', 'arma-panel'); ?>
                                    <strong><?php echo esc_html($order['tracking_method']); ?></strong>
                                    —
                                <?php endif; ?>
                                <?php esc_html_e('کد رهگیری:', 'arma-panel'); ?>
                                <strong dir="ltr" style="letter-spacing: 1px; color: var(--arma-primary);"><?php echo esc_html($order['tracking_code']); ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <h3 style="font-size: 1rem; margin-bottom: 12px;"><?php esc_html_e('اقلام سفارش', 'arma-panel'); ?></h3>
                <div class="arma-order-list" style="padding: 0;">
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="arma-order-item">
                            <div class="arma-order-item__right">
                                <div class="arma-order-item__thumb">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>">
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="arma-order-item__info">
                                    <span class="arma-order-item__title"><?php echo esc_html($item['name']); ?></span>
                                    <span class="arma-order-item__date"><?php printf(esc_html__('تعداد: %d', 'arma-panel'), (int)$item['quantity']); ?></span>
                                </div>
                            </div>
                            <div class="arma-order-item__left">
                                <span class="arma-order-item__price"><?php echo esc_html(function_exists('arma_format_toman') ? arma_format_toman($item['total']) : (string)$item['total']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Orders List -->
    <div class="arma-card">
        <div class="arma-card-header">
            <h2 class="arma-card-header__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span><?php esc_html_e('همه سفارش‌های من', 'arma-panel'); ?></span>
            </h2>
        </div>

        <div class="arma-order-list">
            <?php
            $orders = $section_data['orders'] ?? [];
            if (!empty($orders)):
                foreach ($orders as $order):
            ?>
                <a href="<?php echo esc_url($ordersBaseUrl . $order['id'] . '/'); ?>" class="arma-order-item">
                    <div class="arma-order-item__right">
                        <div class="arma-order-item__thumb">
                            <?php if (!empty($order['first_item_image'])): ?>
                                <img src="<?php echo esc_url($order['first_item_image']); ?>" alt="<?php echo esc_attr($order['first_item_title']); ?>">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="arma-order-item__info">
                            <span class="arma-order-item__title"><?php echo esc_html($order['first_item_title']); ?></span>
                            <span class="arma-order-item__date"><?php echo esc_html($order['date_persian']); ?></span>
                        </div>
                    </div>

                    <div class="arma-order-item__left">
                        <span class="arma-order-item__price"><?php echo esc_html($order['total_formatted']); ?></span>
                        <span class="arma-badge" style="color: <?php echo esc_attr($order['status_color']); ?>; background-color: <?php echo esc_attr($order['status_bg']); ?>;">
                            <?php echo esc_html($order['status_label']); ?>
                        </span>
                    </div>
                </a>
            <?php
                endforeach;
            else:
            ?>
                <div class="arma-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    </svg>
                    <p><?php esc_html_e('شما در حال حاضر هیچ سفارشی ندارید.', 'arma-panel'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
