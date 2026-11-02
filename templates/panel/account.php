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

$savedNotice  = false;
$savedSection = '';

// Handle profile update / avatar personalization if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arma_account_nonce'])) {
    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['arma_account_nonce'])), 'arma_update_account')) {
        $userId = get_current_user_id();

        // 1) Profile names
        if (isset($_POST['first_name'], $_POST['last_name'])) {
            $firstName = sanitize_text_field(wp_unslash($_POST['first_name']));
            $lastName  = sanitize_text_field(wp_unslash($_POST['last_name']));

            wp_update_user([
                'ID'           => $userId,
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'display_name' => trim($firstName . ' ' . $lastName),
            ]);

            $user['first_name']   = $firstName;
            $user['last_name']    = $lastName;
            $user['display_name'] = trim($firstName . ' ' . $lastName);

            $savedNotice  = true;
            $savedSection = 'profile';
        }

    }
}

$defaultAvatarUrl = ARMA_PANEL_URL . 'assets/images/default-user.webp';
?>

<div class="arma-account-grid">

    <!-- ── Summary card (right) ── -->
    <aside class="arma-card arma-account-side">
        <div class="arma-account-side__avatar">
            <img src="<?php echo esc_url($user['avatar_url'] ?? $defaultAvatarUrl); ?>" alt="<?php echo esc_attr($user['display_name']); ?>" onerror="this.src='<?php echo esc_url($defaultAvatarUrl); ?>'">
        </div>
        <h3 class="arma-account-side__name"><?php echo esc_html($user['display_name'] ?: '---'); ?></h3>
        <span class="arma-account-side__phone" dir="ltr"><?php echo esc_html($user['phone_display'] ?: '---'); ?></span>

        <div class="arma-account-side__status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <span><?php esc_html_e('حساب تایید شده و فعال', 'arma-panel'); ?></span>
        </div>

        <ul class="arma-account-side__meta">
            <li>
                <span class="arma-dot arma-dot--info" aria-hidden="true"></span>
                <span><?php esc_html_e('روش ورود:', 'arma-panel'); ?></span>
                <strong><?php esc_html_e('پیامکی (OTP)', 'arma-panel'); ?></strong>
            </li>
        </ul>
    </aside>

    <!-- ── Forms (left) ── -->
    <div class="arma-account-main">

        <?php if ($savedNotice): ?>
            <div class="arma-notice arma-notice--success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?php esc_html_e('اطلاعات نام و نام خانوادگی با موفقیت بروزرسانی شد.', 'arma-panel'); ?>
            </div>
        <?php endif; ?>

        <!-- Profile details -->
        <div class="arma-card arma-card--hover">
            <div class="arma-card-header">
                <h2 class="arma-card-header__title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span><?php esc_html_e('جزئیات حساب کاربری', 'arma-panel'); ?></span>
                </h2>
            </div>

            <form method="post" action="" style="padding: 24px;">
                <?php wp_nonce_field('arma_update_account', 'arma_account_nonce'); ?>

                <div class="arma-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- First Name -->
                    <div class="arma-form-group">
                        <label class="arma-form-label" for="first_name"><?php esc_html_e('نام', 'arma-panel'); ?></label>
                        <input type="text" id="first_name" name="first_name" class="arma-form-control" value="<?php echo esc_attr($user['first_name'] ?? ''); ?>" required>
                    </div>

                    <!-- Last Name -->
                    <div class="arma-form-group">
                        <label class="arma-form-label" for="last_name"><?php esc_html_e('نام خانوادگی', 'arma-panel'); ?></label>
                        <input type="text" id="last_name" name="last_name" class="arma-form-control" value="<?php echo esc_attr($user['last_name'] ?? ''); ?>" required>
                    </div>

                    <!-- Phone Number (Canonical, Protected) -->
                    <div class="arma-form-group" style="grid-column: span 2;">
                        <label class="arma-form-label"><?php esc_html_e('شماره تلفن همراه (شناسه اصلی ورود)', 'arma-panel'); ?></label>
                        <input type="text" class="arma-form-control" value="<?php echo esc_attr($user['phone_display'] ?? ''); ?>" disabled style="background-color: #f1f5f9; cursor: not-allowed; direction: ltr;">
                        <small style="color: var(--arma-text-muted); font-size: 0.78rem; display: block; margin-top: 6px;">
                            <?php esc_html_e('شماره همراه جهت ورود و دریافت کد پیامکی استفاده می‌شود و تغییر آن نیازمند هماهنگی با پشتیبانی است.', 'arma-panel'); ?>
                        </small>
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="arma-btn arma-btn--primary">
                        <?php esc_html_e('ذخیره تغییرات', 'arma-panel'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
