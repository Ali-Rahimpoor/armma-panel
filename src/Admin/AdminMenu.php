<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Admin;

use Arma\ArmaPanel\Admin\Controllers\AdminNotificationController;
use Arma\ArmaPanel\Admin\Controllers\AdminTicketController;
use Arma\ArmaPanel\Admin\Controllers\CleanupController;
use Arma\ArmaPanel\Admin\Controllers\SettingsController;
use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Support\ProxyDetector;

/**
 * WordPress Admin Menu and Submenu Registration.
 */
class AdminMenu {

    /** User meta key remembering the proxy-notice dismissal per admin. */
    public const PROXY_NOTICE_DISMISSED_META = 'arma_proxy_notice_dismissed';

    private SettingsManager $settings;
    private SettingsController $settingsController;
    private AdminTicketController $ticketController;
    private AdminNotificationController $notificationController;
    private CleanupController $cleanupController;

    public function __construct(
        SettingsManager $settings,
        SettingsController $settingsController,
        AdminTicketController $ticketController,
        AdminNotificationController $notificationController,
        CleanupController $cleanupController
    ) {
        $this->settings               = $settings;
        $this->settingsController     = $settingsController;
        $this->ticketController       = $ticketController;
        $this->notificationController = $notificationController;
        $this->cleanupController      = $cleanupController;
    }

    public function registerHooks(): void {
        add_action('admin_menu', [$this, 'registerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_filter('upload_mimes', [$this, 'allowFontUploads']);
        add_action('admin_notices', [$this, 'renderProxyNotice']);
    }

    /**
     * Allow administrators to upload web font files to the media library
     * (used by the custom dashboard font selector).
     *
     * @param array<string, string> $mimes
     * @return array<string, string>
     */
    public function allowFontUploads(array $mimes): array {
        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            $mimes['woff']  = 'font/woff';
            $mimes['woff2'] = 'font/woff2';
            $mimes['ttf']   = 'font/ttf';
            $mimes['otf']   = 'font/otf';
        }

        return $mimes;
    }

    public function registerMenus(): void {
        $capability = 'manage_options';

        /*
         * v1.11.0: unanswered-ticket bubble. Tickets created or re-opened by
         * customers sit in status "pending" until an admin replies — the count
         * is surfaced on BOTH the top-level menu and the tickets submenu, so a
         * new ticket is impossible to miss.
         */
        $pendingTickets = 0;
        if ($this->settings->isModuleEnabled('tickets') && method_exists($this->ticketController, 'getPendingTicketCount')) {
            $pendingTickets = $this->ticketController->getPendingTicketCount();
        }
        $badge = $pendingTickets > 0
            ? ' <span class="awaiting-mod count-' . (int) $pendingTickets . '"><span class="pending-count">' . (int) $pendingTickets . '</span></span>'
            : '';

        // 1. Top Level Menu: آرما پنل
        add_menu_page(
            __('آرما پنل', 'arma-panel'),
            __('آرما پنل', 'arma-panel') . $badge,
            $capability,
            'arma-panel-settings',
            [$this->settingsController, 'renderSettingsPage'],
            'dashicons-id-alt',
            56
        );

        // 2. Submenu: تنظیمات
        add_submenu_page(
            'arma-panel-settings',
            __('تنظیمات آرما پنل', 'arma-panel'),
            __('تنظیمات', 'arma-panel'),
            $capability,
            'arma-panel-settings',
            [$this->settingsController, 'renderSettingsPage']
        );

        // 3. Submenu: مدیریت تیکت‌ها
        if ($this->settings->isModuleEnabled('tickets')) {
            add_submenu_page(
                'arma-panel-settings',
                __('مدیریت تیکت‌ها', 'arma-panel'),
                __('تیکت‌ها', 'arma-panel') . $badge,
                $capability,
                'arma-panel-tickets',
                [$this->ticketController, 'renderPage']
            );
        }

        // 4. Submenu: اطلاعیه‌ها
        if ($this->settings->isModuleEnabled('notifications')) {
            add_submenu_page(
                'arma-panel-settings',
                __('اطلاعیه‌ها', 'arma-panel'),
                __('اطلاعیه‌ها', 'arma-panel'),
                $capability,
                'arma-panel-notifications',
                [$this->notificationController, 'renderPage']
            );
        }

        // 5. Submenu: ابزارها و پاک‌سازی
        add_submenu_page(
            'arma-panel-settings',
            __('ابزارها و پاک‌سازی پایگاه داده', 'arma-panel'),
            __('ابزارها', 'arma-panel'),
            $capability,
            'arma-panel-tools',
            [$this->cleanupController, 'renderPage']
        );
    }

    /**
     * Enqueue admin assets strictly on Arma Panel admin pages.
     */
    public function enqueueAdminAssets(string $hook): void {
        if (strpos($hook, 'arma-panel') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /**
     * Yellow admin notice: the site looks like it sits behind a CDN/proxy
     * while the "trusted proxy" toggle is off — one shared throttle bucket
     * for every visitor. Shown ONLY on Arma Panel pages, ONLY to admins,
     * and dismissible per user (user meta).
     */
    public function renderProxyNotice(): void {
        // 1. Dismissal (GET link) — remember it for this admin. The link
        //    carries a nonce (wp_nonce_url); verify it when available.
        if (isset($_GET['arma_dismiss_proxy_notice'])
            && (int) $_GET['arma_dismiss_proxy_notice'] === 1
            && function_exists('current_user_can') && current_user_can('manage_options')
            && function_exists('get_current_user_id') && function_exists('update_user_meta')
        ) {
            if (function_exists('check_admin_referer')) {
                check_admin_referer('arma_dismiss_proxy_notice');
            }

            update_user_meta(get_current_user_id(), self::PROXY_NOTICE_DISMISSED_META, 1);

            return;
        }

        // 2. Arma Panel admin pages only — the warning is about this plugin.
        if (!$this->isArmaAdminPage()) {
            return;
        }

        // 3. Admins only.
        if (function_exists('current_user_can') && !current_user_can('manage_options')) {
            return;
        }

        // 4. Already dismissed by this admin?
        if (function_exists('get_user_meta') && function_exists('get_current_user_id')
            && (int) get_user_meta(get_current_user_id(), self::PROXY_NOTICE_DISMISSED_META, true) === 1
        ) {
            return;
        }

        // 5. Only warn while the toggle is OFF and the request smells proxied.
        if ((bool) $this->settings->get('authentication.trusted_proxy', false)) {
            return;
        }

        if (!ProxyDetector::isBehindProxy()) {
            return;
        }

        $dismissUrl = '';
        if (function_exists('add_query_arg') && function_exists('wp_nonce_url')) {
            $dismissUrl = (string) wp_nonce_url(
                (string) add_query_arg('arma_dismiss_proxy_notice', '1'),
                'arma_dismiss_proxy_notice'
            );
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Arma Panel:</strong>
                <?php echo esc_html(ProxyDetector::warningMessage()); ?>
                <?php if ($dismissUrl !== '') : ?>
                    <a href="<?php echo esc_url($dismissUrl); ?>" style="text-decoration:none;"><?php esc_html_e('نمایش نده', 'arma-panel'); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    /**
     * Is the current admin screen one of OUR pages? (menu slugs all start
     * with "arma-panel").
     */
    private function isArmaAdminPage(): bool {
        $page = isset($_GET['page'])
            ? (string) wp_unslash($_GET['page'])
            : '';

        if ($page !== '') {
            return strpos($page, 'arma-panel') === 0;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();

            if ($screen && !empty($screen->id)) {
                return strpos((string) $screen->id, 'arma-panel') !== false;
            }
        }

        return false;
    }
}
