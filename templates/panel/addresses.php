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

$addresses = $section_data['addresses'] ?? ['billing' => [], 'shipping' => []];
$billing   = $addresses['billing'] ?? [];
$shipping  = $addresses['shipping'] ?? [];

/*
 * This template only ever DISPLAYS data: the POST is handled by
 * CustomerPanelController::maybeSaveAddress (nonce, AddressService,
 * post-redirect-get) BEFORE the template renders. There is deliberately
 * no direct-POST fallback here — it would save phone/address without
 * any validation, bypassing the service layer.
 */

$errorMessage  = '';
$savedNotice   = '';
$errorParam    = isset($_GET['arma_error']) ? sanitize_text_field(wp_unslash($_GET['arma_error'])) : '';
$savedParam    = isset($_GET['arma_saved']) ? sanitize_text_field(wp_unslash($_GET['arma_saved'])) : '';

if ($savedParam === 'billing') {
    $savedNotice = __('نشانی صورت‌حساب با موفقیت بروزرسانی شد.', 'arma-panel');
} elseif ($savedParam === 'shipping') {
    $savedNotice = __('نشانی تحویل سفارش با موفقیت بروزرسانی شد.', 'arma-panel');
} elseif ($errorParam !== '') {
    $errorMessage = __('ذخیره نشانی انجام نشد؛ لطفاً مقادیر وارد‌شده (به‌ویژه شماره موبایل) را بررسی کنید.', 'arma-panel');
}
?>

<?php if (!empty($savedNotice)): ?>
    <div style="padding: 12px 16px; margin-bottom: 20px; background: var(--arma-success-bg); color: var(--arma-success); border-radius: var(--arma-radius-sm); font-size: 0.9rem;">
        <?php echo esc_html($savedNotice); ?>
    </div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
    <div style="padding: 12px 16px; margin-bottom: 20px; background: var(--arma-danger-bg, #fef2f2); color: var(--arma-danger, #b91c1c); border-radius: var(--arma-radius-sm); font-size: 0.9rem;">
        <?php echo esc_html($errorMessage); ?>
    </div>
<?php endif; ?>

<div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- 1. Billing Address Card & Editor -->
    <div class="arma-card">
        <div class="arma-card-header">
            <h2 class="arma-card-header__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span><?php esc_html_e('نشانی صورت‌حساب', 'arma-panel'); ?></span>
            </h2>
            <button type="button" class="arma-btn arma-btn--outline arma-btn--sm" onclick="document.getElementById('arma-billing-form-wrap').style.display = document.getElementById('arma-billing-form-wrap').style.display === 'none' ? 'block' : 'none';">
                <?php esc_html_e('ویرایش نشانی', 'arma-panel'); ?>
            </button>
        </div>

        <div style="padding: 20px;">
            <!-- Current Display -->
            <div id="arma-billing-display">
                <?php if (!empty($billing['address_1'])): ?>
                    <p style="margin: 0 0 6px; font-weight: 700; color: var(--arma-heading);">
                        <?php echo esc_html(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')); ?>
                    </p>
                    <p style="color: var(--arma-text-muted); font-size: 0.9rem; margin: 0 0 8px; line-height: 1.6;">
                        <?php echo esc_html(($billing['state'] ?? '') . '، ' . ($billing['city'] ?? '') . '، ' . ($billing['address_1'] ?? '')); ?>
                    </p>
                    <div style="display: flex; gap: 16px; font-size: 0.82rem; color: var(--arma-text);">
                        <span><strong><?php esc_html_e('کد پستی:', 'arma-panel'); ?></strong> <?php echo esc_html($billing['postcode'] ?? '---'); ?></span>
                        <span><strong><?php esc_html_e('تماس:', 'arma-panel'); ?></strong> <?php echo esc_html($billing['phone'] ?? '---'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="arma-empty" style="padding: 16px 0;">
                        <p><?php esc_html_e('نشانی صورت‌حسابی ثبت نشده است.', 'arma-panel'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Inline Edit Form -->
            <div id="arma-billing-form-wrap" style="display: <?php echo empty($billing['address_1']) ? 'block' : 'none'; ?>; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--arma-border);">
                <h4 style="margin: 0 0 16px; font-size: 0.95rem; font-weight: 700; color: var(--arma-heading);"><?php esc_html_e('فرم ویرایش نشانی صورت‌حساب', 'arma-panel'); ?></h4>
                <form method="post" action="">
                    <?php wp_nonce_field('arma_save_address', 'arma_address_form_nonce'); ?>
                    <input type="hidden" name="address_type" value="billing">

                    <div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('نام', 'arma-panel'); ?></label>
                            <input type="text" name="first_name" class="arma-form-control" value="<?php echo esc_attr($billing['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('نام خانوادگی', 'arma-panel'); ?></label>
                            <input type="text" name="last_name" class="arma-form-control" value="<?php echo esc_attr($billing['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('استان', 'arma-panel'); ?></label>
                            <input type="text" name="state" class="arma-form-control" value="<?php echo esc_attr($billing['state'] ?? ''); ?>" placeholder="مثال: تهران" required>
                        </div>
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('شهر', 'arma-panel'); ?></label>
                            <input type="text" name="city" class="arma-form-control" value="<?php echo esc_attr($billing['city'] ?? ''); ?>" placeholder="مثال: تهران" required>
                        </div>
                    </div>

                    <div class="arma-form-group">
                        <label class="arma-form-label"><?php esc_html_e('آدرس پستی دقیق', 'arma-panel'); ?></label>
                        <textarea name="address_1" rows="2" class="arma-form-control" placeholder="خیابان، کوچه، پلاک، واحد..." required><?php echo esc_textarea($billing['address_1'] ?? ''); ?></textarea>
                    </div>

                    <div class="arma-form-group">
                        <label class="arma-form-label"><?php esc_html_e('اطلاعات تکمیلی آدرس (اختیاری)', 'arma-panel'); ?></label>
                        <input type="text" name="address_2" class="arma-form-control" value="<?php echo esc_attr($billing['address_2'] ?? ''); ?>" placeholder="واحد، طبقه، کد پستی جایگزین...">
                    </div>

                    <div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('کد پستی (۱۰ رقمی)', 'arma-panel'); ?></label>
                            <input type="text" name="postcode" class="arma-form-control" value="<?php echo esc_attr($billing['postcode'] ?? ''); ?>" required>
                        </div>
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('شماره تماس', 'arma-panel'); ?></label>
                            <input type="text" name="phone" class="arma-form-control" value="<?php echo esc_attr($billing['phone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" class="arma-btn arma-btn--primary arma-btn--sm">
                            <?php esc_html_e('ذخیره نشانی صورت‌حساب', 'arma-panel'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. Shipping Address Card & Editor -->
    <div class="arma-card">
        <div class="arma-card-header">
            <h2 class="arma-card-header__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
                <span><?php esc_html_e('نشانی تحویل سفارش (ارسال)', 'arma-panel'); ?></span>
            </h2>
            <button type="button" class="arma-btn arma-btn--outline arma-btn--sm" onclick="document.getElementById('arma-shipping-form-wrap').style.display = document.getElementById('arma-shipping-form-wrap').style.display === 'none' ? 'block' : 'none';">
                <?php esc_html_e('ویرایش نشانی', 'arma-panel'); ?>
            </button>
        </div>

        <div style="padding: 20px;">
            <!-- Current Display -->
            <div id="arma-shipping-display">
                <?php if (!empty($shipping['address_1'])): ?>
                    <p style="margin: 0 0 6px; font-weight: 700; color: var(--arma-heading);">
                        <?php echo esc_html(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')); ?>
                    </p>
                    <p style="color: var(--arma-text-muted); font-size: 0.9rem; margin: 0 0 8px; line-height: 1.6;">
                        <?php echo esc_html(($shipping['state'] ?? '') . '، ' . ($shipping['city'] ?? '') . '، ' . ($shipping['address_1'] ?? '')); ?>
                    </p>
                    <p style="font-size: 0.82rem; color: var(--arma-text); margin: 0;">
                        <strong><?php esc_html_e('کد پستی:', 'arma-panel'); ?></strong> <?php echo esc_html($shipping['postcode'] ?? '---'); ?>
                    </p>
                <?php else: ?>
                    <div class="arma-empty" style="padding: 16px 0;">
                        <p><?php esc_html_e('نشانی تحویل سفارشی ثبت نشده است.', 'arma-panel'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Inline Edit Form -->
            <div id="arma-shipping-form-wrap" style="display: <?php echo empty($shipping['address_1']) ? 'block' : 'none'; ?>; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--arma-border);">
                <h4 style="margin: 0 0 16px; font-size: 0.95rem; font-weight: 700; color: var(--arma-heading);"><?php esc_html_e('فرم ویرایش نشانی تحویل سفارش', 'arma-panel'); ?></h4>
                <form method="post" action="">
                    <?php wp_nonce_field('arma_save_address', 'arma_address_form_nonce'); ?>
                    <input type="hidden" name="address_type" value="shipping">

                    <div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('نام تحویل‌گیرنده', 'arma-panel'); ?></label>
                            <input type="text" name="first_name" class="arma-form-control" value="<?php echo esc_attr($shipping['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('نام خانوادگی', 'arma-panel'); ?></label>
                            <input type="text" name="last_name" class="arma-form-control" value="<?php echo esc_attr($shipping['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('استان', 'arma-panel'); ?></label>
                            <input type="text" name="state" class="arma-form-control" value="<?php echo esc_attr($shipping['state'] ?? ''); ?>" placeholder="مثال: تهران" required>
                        </div>
                        <div class="arma-form-group">
                            <label class="arma-form-label"><?php esc_html_e('شهر', 'arma-panel'); ?></label>
                            <input type="text" name="city" class="arma-form-control" value="<?php echo esc_attr($shipping['city'] ?? ''); ?>" placeholder="مثال: تهران" required>
                        </div>
                    </div>

                    <div class="arma-form-group">
                        <label class="arma-form-label"><?php esc_html_e('آدرس پستی تحویل', 'arma-panel'); ?></label>
                        <textarea name="address_1" rows="2" class="arma-form-control" placeholder="خیابان، کوچه، پلاک، واحد..." required><?php echo esc_textarea($shipping['address_1'] ?? ''); ?></textarea>
                    </div>

                    <div class="arma-form-group">
                        <label class="arma-form-label"><?php esc_html_e('اطلاعات تکمیلی آدرس (اختیاری)', 'arma-panel'); ?></label>
                        <input type="text" name="address_2" class="arma-form-control" value="<?php echo esc_attr($shipping['address_2'] ?? ''); ?>" placeholder="واحد، طبقه، تحویل به همسایه...">
                    </div>

                    <div class="arma-form-group">
                        <label class="arma-form-label"><?php esc_html_e('کد پستی (۱۰ رقمی)', 'arma-panel'); ?></label>
                        <input type="text" name="postcode" class="arma-form-control" value="<?php echo esc_attr($shipping['postcode'] ?? ''); ?>" required>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" class="arma-btn arma-btn--primary arma-btn--sm">
                            <?php esc_html_e('ذخیره نشانی تحویل', 'arma-panel'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
