<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Core;

use Arma\ArmaPanel\Admin\AdminMenu;
use Arma\ArmaPanel\Admin\Controllers\AdminNotificationController;
use Arma\ArmaPanel\Admin\Controllers\AdminTicketController;
use Arma\ArmaPanel\Admin\Controllers\CleanupController;
use Arma\ArmaPanel\Admin\Controllers\SettingsController;
use Arma\ArmaPanel\Database\DatabaseManager;
use Arma\ArmaPanel\Modules\Authentication\AuthController;
use Arma\ArmaPanel\Modules\Authentication\AuthenticationModule;
use Arma\ArmaPanel\Modules\Authentication\AuthService;
use Arma\ArmaPanel\Modules\CustomerPanel\CustomerPanelController;
use Arma\ArmaPanel\Modules\CustomerPanel\CustomerPanelModule;
use Arma\ArmaPanel\Modules\CustomerPanel\NavigationManager;
use Arma\ArmaPanel\Modules\CustomerPanel\PanelPageManager;
use Arma\ArmaPanel\Modules\CustomerPanel\PanelRouter;
use Arma\ArmaPanel\Modules\LuckyWheel\LuckyWheelController;
use Arma\ArmaPanel\Modules\LuckyWheel\LuckyWheelModule;
use Arma\ArmaPanel\Modules\LuckyWheel\LuckyWheelService;
use Arma\ArmaPanel\Modules\Notifications\NotificationController;
use Arma\ArmaPanel\Modules\Notifications\NotificationRepository;
use Arma\ArmaPanel\Modules\Notifications\NotificationsModule;
use Arma\ArmaPanel\Modules\Notifications\NotificationService;
use Arma\ArmaPanel\Modules\OTP\OTPGenerator;
use Arma\ArmaPanel\Modules\OTP\OTPModule;
use Arma\ArmaPanel\Modules\OTP\OTPRateLimiter;
use Arma\ArmaPanel\Modules\OTP\OTPRepository;
use Arma\ArmaPanel\Modules\OTP\OTPService;
use Arma\ArmaPanel\Modules\OTP\OTPVerifier;
use Arma\ArmaPanel\Modules\SMS\SMSManager;
use Arma\ArmaPanel\Modules\SMS\SMSModule;
use Arma\ArmaPanel\Modules\Tickets\TicketController;
use Arma\ArmaPanel\Modules\Tickets\TicketMessageRepository;
use Arma\ArmaPanel\Modules\Tickets\TicketRepository;
use Arma\ArmaPanel\Modules\Tickets\TicketsModule;
use Arma\ArmaPanel\Modules\Wishlist\WishlistModule;
use Arma\ArmaPanel\Modules\Tickets\TicketService;
use Arma\ArmaPanel\Modules\WooCommerce\AddressService;
use Arma\ArmaPanel\Modules\WooCommerce\AbandonedCartManager;
use Arma\ArmaPanel\Modules\WooCommerce\GuestCheckoutManager;
use Arma\ArmaPanel\Modules\WooCommerce\ShipmentTrackingManager;
use Arma\ArmaPanel\Modules\WooCommerce\OrderService;
use Arma\ArmaPanel\Modules\WooCommerce\WooCommerceManager;
use Arma\ArmaPanel\Modules\WooCommerce\WooCommerceModule;
use Arma\ArmaPanel\Support\Phone\PhoneService;

/**
 * Main Application orchestrator for Arma Panel.
 */
class Application {

    private static ?self $instance = null;
    private Container $container;
    private bool $booted = false;

    public function __construct() {
        $this->container = new Container();
        $this->registerCoreBindings();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getContainer(): Container {
        return $this->container;
    }

    /**
     * Bind all core services and repositories into the dependency container.
     */
    private function registerCoreBindings(): void {
        $c = $this->container;

        // Core Managers
        $c->singleton('settings', fn() => new SettingsManager());
        $c->singleton('phone', fn($c) => new PhoneService($c->get('settings')));
        $c->singleton('db', fn() => new DatabaseManager());

        // SMS System
        $c->singleton('sms_manager', fn($c) => new SMSManager($c->get('settings')));

        // OTP System
        $c->singleton('otp_repo', fn() => new OTPRepository());
        $c->singleton('otp_generator', fn() => new OTPGenerator());
        $c->singleton('otp_rate_limiter', fn($c) => new OTPRateLimiter($c->get('otp_repo')));
        $c->singleton('otp_verifier', fn($c) => new OTPVerifier($c->get('otp_repo'), $c->get('otp_rate_limiter'), $c->get('settings')));
        $c->singleton('otp_service', fn($c) => new OTPService(
            $c->get('otp_generator'),
            $c->get('otp_repo'),
            $c->get('otp_rate_limiter'),
            $c->get('otp_verifier'),
            $c->get('settings'),
            $c->get('phone'),
            $c->get('sms_manager')
        ));

        // Authentication System
        $c->singleton('auth_service', fn($c) => new AuthService(
            $c->get('phone'),
            $c->get('otp_service'),
            $c->get('settings')
        ));
        $c->singleton('auth_controller', fn($c) => new AuthController($c->get('auth_service')));

        // WooCommerce Integration
        $c->singleton('order_service', fn() => new OrderService());
        $c->singleton('address_service', fn($c) => new AddressService($c->get('phone')));
        $c->singleton('wc_manager', fn($c) => new WooCommerceManager(
            $c->get('settings'),
            $c->get('sms_manager'),
            $c->get('order_service'),
            $c->get('address_service')
        ));
        $c->singleton('guest_checkout_manager', fn($c) => new GuestCheckoutManager(
            $c->get('settings')
        ));
        $c->singleton('abandoned_cart_manager', fn($c) => new AbandonedCartManager(
            $c->get('settings'),
            $c->get('sms_manager'),
            $c->get('notif_service')
        ));
        $c->singleton('shipment_tracking_manager', fn($c) => new ShipmentTrackingManager(
            $c->get('settings'),
            $c->get('sms_manager'),
            $c->get('notif_service')
        ));

        // Notifications System
        $c->singleton('notif_repo', fn() => new NotificationRepository());
        $c->singleton('notif_service', fn($c) => new NotificationService($c->get('notif_repo')));
        $c->singleton('notif_controller', fn($c) => new NotificationController($c->get('notif_service')));

        // Lucky Wheel System
        $c->singleton('lucky_wheel_service', fn($c) => new LuckyWheelService(
            $c->get('settings'),
            $c->get('notif_service')
        ));
        $c->singleton('lucky_wheel_controller', fn($c) => new LuckyWheelController($c->get('lucky_wheel_service')));

        $c->singleton('wishlist_service', fn($c) => new \Arma\ArmaPanel\Modules\Wishlist\WishlistService(
            $c->get('settings')
        ));
        $c->singleton('wishlist_controller', fn($c) => new \Arma\ArmaPanel\Modules\Wishlist\WishlistController(
            $c->get('wishlist_service'),
            $c->get('settings')
        ));

        // Tickets System
        $c->singleton('ticket_repo', fn() => new TicketRepository());
        $c->singleton('ticket_msg_repo', fn() => new TicketMessageRepository());
        $c->singleton('ticket_service', fn($c) => new TicketService(
            $c->get('ticket_repo'),
            $c->get('ticket_msg_repo'),
            $c->get('notif_service')
        ));
        $c->singleton('ticket_controller', fn($c) => new TicketController($c->get('ticket_service')));

        // Customer Panel
        $c->singleton('nav_manager', fn($c) => new NavigationManager(
            $c->get('settings'),
            $c->get('notif_service')
        ));
        $c->singleton('panel_controller', fn($c) => new CustomerPanelController(
            $c->get('settings'),
            $c->get('nav_manager'),
            $c->get('phone'),
            $c->get('order_service'),
            $c->get('address_service'),
            $c->get('ticket_service'),
            $c->get('notif_service'),
            $c->get('lucky_wheel_service'),
            $c->get('wishlist_service')
        ));
        $c->singleton('panel_page_manager', fn($c) => new PanelPageManager($c->get('settings')));
        $c->singleton('panel_router', fn($c) => new PanelRouter(
            $c->get('settings'),
            $c->get('panel_controller'),
            $c->get('panel_page_manager')
        ));

        // Admin System
        $c->singleton('admin_settings_controller', fn($c) => new SettingsController(
            $c->get('settings'),
            $c->get('sms_manager')
        ));
        $c->singleton('admin_ticket_controller', fn($c) => new AdminTicketController($c->get('ticket_service')));
        $c->singleton('admin_notification_controller', fn($c) => new AdminNotificationController($c->get('notif_service')));
        $c->singleton('admin_cleanup_controller', fn($c) => new CleanupController(
            $c->get('otp_repo'),
            $c->get('ticket_msg_repo'),
            $c->get('notif_repo')
        ));
        $c->singleton('admin_menu', fn($c) => new AdminMenu(
            $c->get('settings'),
            $c->get('admin_settings_controller'),
            $c->get('admin_ticket_controller'),
            $c->get('admin_notification_controller'),
            $c->get('admin_cleanup_controller')
        ));

        // Module Manager
        $c->singleton('module_manager', fn($c) => new ModuleManager($c->get('settings')));
    }

    /**
     * Boot the plugin application.
     */
    public function boot(): void {
        if ($this->booted) {
            return;
        }

        // 1. Run migrations if DB is outdated
        /** @var DatabaseManager $db */
        $db = $this->container->get('db');
        $db->migrate();

        // 1b. One-time: promote the plugin's hot options to autoload=yes.
        $db->migrateAutoloadFlags();

        // 2. Load translations
        load_plugin_textdomain('arma-panel', false, dirname(ARMA_PANEL_BASENAME) . '/languages');

        // 3. Register and boot all modules
        /** @var ModuleManager $moduleManager */
        $moduleManager = $this->container->get('module_manager');

        $moduleManager->register(new AuthenticationModule($this->container->get('auth_controller')));
        $moduleManager->register(new OTPModule());
        $moduleManager->register(new SMSModule());
        $moduleManager->register(new WooCommerceModule(
            $this->container->get('wc_manager'),
            $this->container->get('guest_checkout_manager'),
            $this->container->get('shipment_tracking_manager'),
            $this->container->get('abandoned_cart_manager')
        ));
        $moduleManager->register(new CustomerPanelModule($this->container->get('panel_router')));
        $moduleManager->register(new TicketsModule($this->container->get('ticket_controller')));
        $moduleManager->register(new NotificationsModule($this->container->get('notif_controller')));
        $moduleManager->register(new LuckyWheelModule($this->container->get('lucky_wheel_controller')));
        $moduleManager->register(new WishlistModule($this->container->get('wishlist_controller')));

        $moduleManager->bootAll();

        // 4. Admin hooks
        if (is_admin()) {
            /** @var AdminMenu $adminMenu */
            $adminMenu = $this->container->get('admin_menu');
            $adminMenu->registerHooks();
        }

        $this->booted = true;
    }

    /**
     * Plugin activation callback.
     */
    public static function activate(): void {
        $app = self::getInstance();

        // 1. Run migrations.
        /** @var DatabaseManager $db */
        $db = $app->getContainer()->get('db');
        $db->migrate();

        // 2. Create the real panel page BEFORE flushing rewrite rules so the
        //    rules (which point at that page) resolve correctly from the
        //    very first request after activation.
        /** @var \Arma\ArmaPanel\Modules\CustomerPanel\PanelPageManager $pageManager */
        $pageManager = $app->getContainer()->get('panel_page_manager');
        $pageManager->ensurePage();

        // 3. Register rewrite rules in THIS request, then flush — otherwise
        //    the flush would persist rules without the panel and every panel
        //    URL would 404 (the classic activation-flush bug).
        /** @var \Arma\ArmaPanel\Modules\CustomerPanel\PanelRouter $router */
        $router = $app->getContainer()->get('panel_router');
        $router->addRewriteRules();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback.
     */
    public static function deactivate(): void {
        // Never leave our cron events behind on deactivation.
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('arma_panel_abandoned_check');
        }

        flush_rewrite_rules();
    }
}
