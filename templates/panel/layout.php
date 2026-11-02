<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var string $current_page
 * @var array<string, mixed> $user
 * @var array<\Arma\ArmaPanel\Modules\CustomerPanel\NavigationItem> $nav_items
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var array<string, mixed> $section_data
 */

/* ---------------------------------------------------------------------
 * Appearance settings -> CSS variables
 * ------------------------------------------------------------------- */
$primaryColor   = esc_attr($settings->get('appearance.primary_color', '#e11d48'));
$secondaryColor = esc_attr($settings->get('appearance.secondary_color', '#475569'));
$bgColor        = esc_attr($settings->get('appearance.background_color', '#f4f5f8'));
$cardColor      = esc_attr($settings->get('appearance.card_color', '#ffffff'));
$textColor      = esc_attr($settings->get('appearance.text_color', '#1e293b'));
$headingColor   = esc_attr($settings->get('appearance.heading_color', '#0f172a'));
$borderColor    = esc_attr($settings->get('appearance.border_color', '#f1f5f9'));
// Links + sidebar active state FOLLOW the primary color automatically (v1.1.1)
// — changing «رنگ اصلی» re-colors every brand-colored element in the panel.
$linkColor      = $primaryColor;
$successColor   = esc_attr($settings->get('appearance.success_color', '#10b981'));
$warningColor   = esc_attr($settings->get('appearance.warning_color', '#f59e0b'));
$dangerColor    = esc_attr($settings->get('appearance.danger_color', '#ef4444'));

/* Sidebar */
$sidebarBg         = esc_attr($settings->get('appearance.sidebar_bg', $cardColor ?: '#ffffff'));
$sidebarText       = esc_attr($settings->get('appearance.sidebar_text', '#475569'));
$sidebarActiveBg   = $primaryColor . '1f';   // ~12% tint of the primary color
$sidebarActiveText = $primaryColor;
$sidebarHoverBg    = esc_attr($settings->get('appearance.sidebar_hover_bg', '#f8fafc'));
$sidebarBorder     = esc_attr($settings->get('appearance.sidebar_border', '#f1f5f9'));

/* Cards, inputs, buttons */
$welcomeBg     = esc_attr($settings->get('appearance.welcome_bg', '#f1ece2'));
$inputBg       = esc_attr($settings->get('appearance.input_bg', '#ffffff'));
$inputBorder   = esc_attr($settings->get('appearance.input_border', '#cbd5e1'));
$buttonText    = esc_attr($settings->get('appearance.button_text', '#ffffff'));
$cardRadius    = (int) $settings->get('appearance.card_radius', 16);
$inputRadius   = (int) $settings->get('appearance.input_radius', 12);
$buttonRadius  = (int) $settings->get('appearance.button_radius', 12);

/* ---------------------------------------------------------------------
 * Font setup (shared helper — same font on panel & login page)
 * ------------------------------------------------------------------- */
$fontAssets  = function_exists('arma_font_assets') ? arma_font_assets() : ['faces' => '', 'family' => 'inherit'];
$fontFaces   = $fontAssets['faces'];
$fontFamilyCss = $fontAssets['family'] !== '' ? $fontAssets['family'] : 'inherit';

$panelTitle = esc_html($settings->get('general.panel_title', 'حساب کاربری من'));
$logoutUrl = wp_logout_url(home_url('/'));
$unreadCount = (int) ($user['unread_count'] ?? 0);

// ── Mobile-first layout switches (v1.7.0) ──
$isMobile        = function_exists('arma_is_mobile') && arma_is_mobile();
$mobileConf      = (array) $settings->get('dashboard.mobile', []);
$mobileMenuModel = $isMobile ? (string) ($mobileConf['menu_model'] ?? 'toggle') : 'toggle';
$mobileProfile   = $isMobile ? (string) ($mobileConf['profile_card'] ?? 'full') : 'full';
$sidebarClasses  = ['arma-sidebar'];
if ($mobileMenuModel === 'open') {
    $sidebarClasses[] = 'arma-sidebar--menu-open';
}

// Default Avatar
$defaultAvatarUrl = ARMA_PANEL_URL . 'assets/images/default-user.webp';
$avatarUrl = !empty($user['avatar_url']) ? $user['avatar_url'] : $defaultAvatarUrl;

$isWpTheme = function_exists('get_header');
if ($isWpTheme) {
    get_header();
} else {
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($panelTitle); ?> - <?php bloginfo('name'); ?></title>
<?php } ?>

    <!-- Panel stylesheet FIRST so the dynamic variables below OVERRIDE its
         :root defaults (same specificity → document order decides). -->
    <link rel="stylesheet" href="<?php echo esc_url(ARMA_PANEL_URL . 'assets/css/arma-panel.css?v=' . ARMA_PANEL_VERSION); ?>">

    <!-- Arma Panel Dynamic CSS Variables (admin customizer → CSS variables) -->
    <style id="arma-panel-variables">
            <?php echo $fontFaces; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        :root {
            --arma-font: <?php echo $fontFamilyCss; ?>;
            --arma-primary: <?php echo $primaryColor; ?>;
            --arma-primary-hover: <?php echo $primaryColor; ?>ee;
            --arma-primary-light: <?php echo $primaryColor; ?>15;
            --arma-secondary: <?php echo $secondaryColor; ?>;
            --arma-background: <?php echo $bgColor; ?>;
            --arma-card: <?php echo $cardColor; ?>;
            --arma-text: <?php echo $textColor; ?>;
            --arma-heading: <?php echo $headingColor; ?>;
            --arma-border: <?php echo $borderColor; ?>;
            --arma-link: <?php echo $linkColor; ?>;
            --arma-success: <?php echo $successColor; ?>;
            --arma-warning: <?php echo $warningColor; ?>;
            --arma-danger: <?php echo $dangerColor; ?>;
            /* Status pills: very light bg derived from the admin's chosen colors */
            --arma-success-bg: <?php echo $successColor; ?>1f;
            --arma-warning-bg: <?php echo $warningColor; ?>1f;
            --arma-danger-bg: <?php echo $dangerColor; ?>1f;
            --arma-welcome-bg: <?php echo $welcomeBg; ?>;
            --arma-sidebar-bg: <?php echo $sidebarBg; ?>;
            --arma-sidebar-text: <?php echo $sidebarText; ?>;
            --arma-sidebar-active-bg: <?php echo $sidebarActiveBg; ?>;
            --arma-sidebar-active-text: <?php echo $sidebarActiveText; ?>;
            --arma-sidebar-hover-bg: <?php echo $sidebarHoverBg; ?>;
            --arma-sidebar-border: <?php echo $sidebarBorder; ?>;
            --arma-input-bg: <?php echo $inputBg; ?>;
            --arma-input-border: <?php echo $inputBorder; ?>;
            --arma-btn-text: <?php echo $buttonText; ?>;
            --arma-radius-sm: <?php echo max(0, $inputRadius - 4); ?>px;
            --arma-radius-md: <?php echo max(0, $inputRadius); ?>px;
            --arma-radius-lg: <?php echo max(0, $cardRadius); ?>px;
            --arma-radius-btn: <?php echo max(0, $buttonRadius); ?>px;
        }
    </style>

    <!-- Panel Data Object for AJAX — v1.10.5: MUST live in <head>, BEFORE the
         section templates render. Section templates (wishlist, ...) attach
         their click handlers in inline scripts during render; when this block
         sat after the content, window.armaPanelData was still undefined there
         and the handlers silently never attached (remove button did nothing). -->
    <script>
        window.armaPanelData = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            ticket_nonce: <?php echo wp_json_encode(wp_create_nonce('arma_ticket_nonce')); ?>,
            notification_nonce: <?php echo wp_json_encode(wp_create_nonce('arma_notification_nonce')); ?>,
            address_nonce: <?php echo wp_json_encode(wp_create_nonce('arma_address_nonce')); ?>,
            wishlist_nonce: <?php echo wp_json_encode(wp_create_nonce('arma_wishlist_nonce')); ?>
        };
        window.armaWheelData = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('arma_wheel_nonce')); ?>
        };
    </script>

<?php if (!$isWpTheme): ?>
</head>
<body class="arma-panel-body">
<?php endif; ?>

    <!-- Main Wrapper (Notice: Custom header topbar removed per user request so active theme header is used!) -->
    <div class="arma-wrapper">
        <div class="arma-layout">
            <!-- Sidebar (Right) -->
            <aside class="<?php echo esc_attr(implode(' ', $sidebarClasses)); ?>" id="arma-sidebar">
                <!-- User Profile Card (v1.7.0: admin controls its phone model:
                     full / minimal (no default account icon) / hidden) -->
                <?php if ($mobileProfile !== 'hidden'): ?>
                <div class="arma-card arma-profile-card<?php echo $mobileProfile === 'minimal' ? ' arma-profile-card--minimal' : ''; ?>">
                    <?php if ($settings->isModuleEnabled('notifications')): ?>
                        <a href="<?php echo esc_url(function_exists('arma_panel_url') ? arma_panel_url('notifications') : home_url('/notifications/')); ?>" class="arma-profile-card__bell" title="<?php esc_attr_e('اطلاعیه‌ها', 'arma-panel'); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <?php if ($unreadCount > 0): ?>
                                <span class="arma-badge-dot"><?php echo esc_html((string)$unreadCount); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($mobileProfile !== 'minimal'): ?>
                    <div class="arma-profile-card__avatar">
                        <img src="<?php echo esc_url($avatarUrl); ?>" alt="<?php echo esc_attr($user['display_name']); ?>" onerror="this.src='<?php echo esc_url($defaultAvatarUrl); ?>'">
                    </div>
                    <?php endif; ?>

                    <h3 class="arma-profile-card__name"><?php echo esc_html($user['display_name']); ?></h3>
                    <?php if ($mobileProfile !== 'minimal' && !empty($user['phone_display'])): ?>
                        <span class="arma-profile-card__phone" dir="ltr"><?php echo esc_html($user['phone_display']); ?></span>
                    <?php endif; ?>

                    <ul class="arma-profile-card__meta">
                        <li>
                            <span class="arma-dot arma-dot--success" aria-hidden="true"></span>
                            <span><?php esc_html_e('وضعیت حساب:', 'arma-panel'); ?></span>
                            <strong><?php esc_html_e('فعال', 'arma-panel'); ?></strong>
                        </li>
                        <li>
                            <span class="arma-dot arma-dot--info" aria-hidden="true"></span>
                            <span><?php esc_html_e('روش ورود:', 'arma-panel'); ?></span>
                            <strong><?php esc_html_e('پیامکی', 'arma-panel'); ?></strong>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Mobile menu toggle (collapsible list — no hidden horizontal scroll).
                     v1.7.0: hidden entirely when the admin picks the always-open menu. -->
                <button type="button" class="arma-nav-toggle" id="arma-nav-toggle" aria-expanded="false" aria-controls="arma-panel-navlist">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    <span><?php esc_html_e('منوی حساب کاربری', 'arma-panel'); ?></span>
                    <svg class="arma-nav-toggle__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <!-- Navigation Card -->
                <div class="arma-card arma-nav">
                    <ul class="arma-nav__list" id="arma-panel-navlist">
                        <?php foreach ($nav_items as $item): ?>
                            <?php
                            $isActive = ($item->getId() === $current_page);
                            $badge = $item->getBadge();
                            if ($item->getId() === 'notifications' && $unreadCount > 0) {
                                $badge = (string) $unreadCount;
                            }
                            ?>
                            <li class="arma-nav__item <?php echo $isActive ? 'arma-nav__item--active' : ''; ?>">
                                <a href="<?php echo esc_url($item->getRoute()); ?>" class="arma-nav__link">
                                    <?php echo $item->getIconSvg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <span><?php echo esc_html($item->getLabel()); ?></span>
                                    <?php if (!empty($badge)): ?>
                                        <span class="arma-nav__badge"><?php echo esc_html($badge); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>

                        <!-- Logout Item -->
                        <li class="arma-nav__item arma-nav__item--logout">
                            <a href="<?php echo esc_url($logoutUrl); ?>" class="arma-nav__link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                <span><?php esc_html_e('خروج از حساب کاربری', 'arma-panel'); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- Main Content (Left) -->
            <main class="arma-content">
                <?php
                // Render section view
                arma_view('panel/' . $current_page, [
                    'user'         => $user,
                    'settings'     => $settings,
                    'section_data' => $section_data,
                ]);
                ?>
            </main>
        </div>
    </div>

    <script src="<?php echo esc_url(ARMA_PANEL_URL . 'assets/js/arma-panel.js?v=' . ARMA_PANEL_VERSION); ?>"></script>

    <script>
    // Collapsible account menu on mobile (all items visible when opened —
    // nothing hidden behind an undiscoverable horizontal scroll).
    (function () {
        var toggle = document.getElementById('arma-nav-toggle');
        var sidebar = document.getElementById('arma-sidebar');
        if (!toggle || !sidebar) return;

        toggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('arma-sidebar--nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // After choosing an item on a multi-section phone layout, collapse again.
        var list = document.getElementById('arma-panel-navlist');
        if (list) {
            list.addEventListener('click', function (e) {
                if (e.target.closest('a')) {
                    sidebar.classList.remove('arma-sidebar--nav-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    })();
    </script>

<?php
if ($isWpTheme) {
    get_footer();
} else {
?>
</body>
</html>
<?php } ?>
