<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 */

$primaryColor = esc_attr($settings->get('appearance.primary_color', '#e11d48'));
$bgColor      = esc_attr($settings->get('appearance.background_color', '#f8fafc'));
$panelTitle   = esc_html($settings->get('general.panel_title', get_bloginfo('name')));
$logoId       = (int) $settings->get('appearance.logo_id', 0);
$logoUrl      = $logoId > 0 ? wp_get_attachment_image_url($logoId, 'full') : '';

// Dedicated login font: locally-bundled Vazirmatn when the admin toggle is on
// (single optimized woff2 from the plugin folder), otherwise the dashboard font.
$loginFont     = function_exists('arma_login_font_assets') ? arma_login_font_assets() : ['faces' => '', 'family' => 'inherit'];
$fontFaces     = $loginFont['faces'];
$fontFamilyCss = $loginFont['family'] !== '' ? $loginFont['family'] : 'inherit';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('ورود یا ثبت‌نام با شماره همراه', 'arma-panel'); ?> - <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(ARMA_PANEL_URL . 'assets/css/arma-panel.css?v=' . ARMA_PANEL_VERSION); ?>">
    <style>
        <?php echo $fontFaces; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        :root {
            --arma-font: <?php echo $fontFamilyCss; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
            --arma-primary: <?php echo $primaryColor; ?>;
            --arma-primary-hover: <?php echo $primaryColor; ?>ee;
            --arma-primary-light: <?php echo $primaryColor; ?>15;
            --arma-background: <?php echo $bgColor; ?>;
        }
    </style>
    <style>
        .arma-auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--arma-background);
            padding: 24px;
        }
        /* Font applies to EVERYTHING on the login page — inputs, buttons and
           selects do NOT inherit font-family by default in browsers. */
        .arma-auth-page,
        .arma-auth-page input,
        .arma-auth-page button,
        .arma-auth-page select,
        .arma-auth-page textarea,
        .arma-auth-page h1, .arma-auth-page h2, .arma-auth-page h3,
        .arma-auth-page p, .arma-auth-page span, .arma-auth-page label,
        .arma-auth-page a, .arma-auth-page strong {
            font-family: var(--arma-font, Tahoma, sans-serif) !important;
        }
        .arma-auth-box {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: var(--arma-radius-lg);
            border: 1px solid var(--arma-border);
            box-shadow: var(--arma-shadow-md);
            padding: 36px 32px;
        }
        .arma-auth-box__logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .arma-auth-box__logo img {
            max-height: 48px;
            width: auto;
        }
        .arma-auth-box__title {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 8px;
            text-align: center;
            color: var(--arma-heading);
        }
        .arma-auth-box__subtitle {
            font-size: 0.85rem;
            color: var(--arma-text-muted);
            text-align: center;
            margin: 0 0 24px;
        }
        .arma-auth-message {
            padding: 10px 14px;
            border-radius: var(--arma-radius-sm);
            font-size: 0.85rem;
            margin-bottom: 16px;
            display: none;
        }
        .arma-auth-message--error {
            background: var(--arma-danger-bg);
            color: var(--arma-danger);
        }
        .arma-auth-message--success {
            background: var(--arma-success-bg);
            color: var(--arma-success);
        }
        .arma-otp-timer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            color: var(--arma-text-muted);
            margin-top: 12px;
        }
    </style>
</head>
<body class="arma-panel-body arma-auth-page">
    <div class="arma-auth-box">
        <div class="arma-auth-box__logo">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php echo esc_attr($panelTitle); ?>">
            <?php else: ?>
                <h2 style="margin: 0; color: var(--arma-primary);"><?php echo esc_html($panelTitle); ?></h2>
            <?php endif; ?>
        </div>

        <h1 class="arma-auth-box__title"><?php esc_html_e('ورود یا ثبت‌نام', 'arma-panel'); ?></h1>
        <p class="arma-auth-box__subtitle"><?php esc_html_e('لطفاً شماره موبایل خود را وارد نمایید.', 'arma-panel'); ?></p>

        <!-- Message Box -->
        <div id="arma-auth-message" class="arma-auth-message"></div>

        <form id="arma-auth-form">
            <!-- Step 1: Phone Input -->
            <div id="arma-step-phone">
                <div class="arma-form-group">
                    <label class="arma-form-label" for="arma-phone-input"><?php esc_html_e('شماره تلفن همراه', 'arma-panel'); ?></label>
                    <input type="tel" id="arma-phone-input" class="arma-form-control" placeholder="۰۹۱۲۳۴۵۶۷۸۹" style="direction: ltr; text-align: center; font-size: 1.1rem; letter-spacing: 2px;" required autofocus>
                </div>
                <button type="button" id="arma-btn-send-otp" class="arma-btn arma-btn--primary" style="width: 100%; padding: 12px;">
                    <?php esc_html_e('دریافت کد تایید', 'arma-panel'); ?>
                </button>
            </div>

            <!-- Step 2: OTP Code Verification -->
            <div id="arma-step-otp" style="display: none;">
                <div style="background: #f8fafc; padding: 10px 14px; border-radius: var(--arma-radius-sm); font-size: 0.85rem; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span><?php esc_html_e('کد تایید ارسال شده به:', 'arma-panel'); ?></span>
                        <strong id="arma-otp-target-phone" style="direction: ltr; display: inline-block;"></strong>
                    </div>
                    <button type="button" id="arma-btn-edit-phone" class="arma-btn arma-btn--outline arma-btn--sm">
                        <?php esc_html_e('ویرایش', 'arma-panel'); ?>
                    </button>
                </div>

                <div class="arma-form-group">
                    <label class="arma-form-label" for="arma-otp-input"><?php esc_html_e('کد تایید پیامک شده', 'arma-panel'); ?></label>
                    <input type="text" id="arma-otp-input" class="arma-form-control" placeholder="- - - - -" maxlength="8" style="direction: ltr; text-align: center; font-size: 1.3rem; letter-spacing: 6px; font-weight: 700;">
                </div>

                <button type="button" id="arma-btn-verify" class="arma-btn arma-btn--primary" style="width: 100%; padding: 12px;">
                    <?php esc_html_e('ورود به حساب', 'arma-panel'); ?>
                </button>

                <div class="arma-otp-timer">
                    <div>
                        <span><?php esc_html_e('ارسال مجدد کد پس از:', 'arma-panel'); ?></span>
                        <strong id="arma-timer-count" style="color: var(--arma-primary); direction: ltr; display: inline-block;">۰۲:۰۰</strong>
                    </div>
                    <button type="button" id="arma-btn-resend" class="arma-btn arma-btn--outline arma-btn--sm" disabled>
                        <?php esc_html_e('ارسال مجدد', 'arma-panel'); ?>
                    </button>
                </div>
            </div>

            <!-- Step 3: Complete Registration (Only for new users) -->
            <div id="arma-step-name" style="display: none;">
                <p style="font-size: 0.85rem; color: var(--arma-text-muted); margin-bottom: 16px;">
                    <?php esc_html_e('شماره همراه شما تایید شد. برای تکمیل عضویت لطفاً نام و نام خانوادگی خود را وارد نمایید.', 'arma-panel'); ?>
                </p>

                <div class="arma-form-group">
                    <label class="arma-form-label" for="arma-firstname-input"><?php esc_html_e('نام', 'arma-panel'); ?></label>
                    <input type="text" id="arma-firstname-input" class="arma-form-control" placeholder="<?php esc_attr_e('نام کوچک', 'arma-panel'); ?>" required>
                </div>

                <div class="arma-form-group">
                    <label class="arma-form-label" for="arma-lastname-input"><?php esc_html_e('نام خانوادگی', 'arma-panel'); ?></label>
                    <input type="text" id="arma-lastname-input" class="arma-form-control" placeholder="<?php esc_attr_e('نام خانوادگی', 'arma-panel'); ?>" required>
                </div>

                <button type="button" id="arma-btn-register" class="arma-btn arma-btn--primary" style="width: 100%; padding: 12px;">
                    <?php esc_html_e('تکمیل ثبت‌نام و ورود', 'arma-panel'); ?>
                </button>
            </div>
        </form>

        <div style="margin-top: 24px; text-align: center;">
            <a href="<?php echo esc_url(home_url('/')); ?>" style="color: var(--arma-text-muted); font-size: 0.82rem; text-decoration: none;">
                &larr; <?php esc_html_e('بازگشت به سایت اصلی', 'arma-panel'); ?>
            </a>
        </div>
    </div>

    <script>
        window.armaAuthData = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('arma_auth_nonce')); ?>,
            redirect_to: <?php echo wp_json_encode(arma_safe_redirect_target(isset($_GET['redirect_to']) ? (string) wp_unslash($_GET['redirect_to']) : '', '')); ?>
        };
    </script>
    <script src="<?php echo esc_url(ARMA_PANEL_URL . 'assets/js/arma-auth.js?v=' . ARMA_PANEL_VERSION); ?>"></script>
</body>
</html>
