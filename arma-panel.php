<?php
/**
 * Plugin Name:       Arma Panel (آرما پنل)
 * Plugin URI:        https://alirahimpoor.ir
 * Description:       جایگزین حرفه‌ای حساب کاربری ووکامرس: ورود و ثبت‌نام بدون رمز با موبایل و کد یک‌بارمصرف، پنل مشتری، تیکت پشتیبانی، گردونه شانس و پیامک پترن.
 * Version:           1.0.0
 * Author:            Ali Rahimpoor (علی رحیم‌پور)
 * Author URI:        https://alirahimpoor.ir 
 * Text Domain:       arma-panel
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * WC requires at least: 6.0
 * Requires Plugins:  woocommerce
 */

declare(strict_types=1);

// Block direct access.
if (!defined('ABSPATH')) {
    exit;
}

/*
 * declare HPOS (custom order tables) compatibility BEFORE
 * WooCommerce initializes. Without this declaration WooCommerce shows the
 * plugin as incompatible when the store switches to HPOS.
 */
add_action('before_woocommerce_init', static function (): void {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ARMA_PANEL_FILE, true);
    }
});

// 1. Define Plugin Constants
define('ARMA_PANEL_VERSION', '1.0.0');
define('ARMA_PANEL_FILE', __FILE__);
define('ARMA_PANEL_PATH', plugin_dir_path(__FILE__));
define('ARMA_PANEL_URL', plugin_dir_url(__FILE__));
define('ARMA_PANEL_BASENAME', plugin_basename(__FILE__));

// 2. Load Composer Autoloader
$autoloader = ARMA_PANEL_PATH . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    // Fallback simple autoloader if vendor directory is not built
    spl_autoload_register(function ($class) {
        $prefix = 'Arma\\ArmaPanel\\';
        $base_dir = ARMA_PANEL_PATH . 'src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
    // Load helper functions
    $helper_file = ARMA_PANEL_PATH . 'src/Support/Helpers/functions.php';
    if (file_exists($helper_file)) {
        require_once $helper_file;
    }
}

// 3. Register Activation & Deactivation Hooks
register_activation_hook(ARMA_PANEL_FILE, function () {
    \Arma\ArmaPanel\Core\Application::activate();
});

register_deactivation_hook(ARMA_PANEL_FILE, function () {
    \Arma\ArmaPanel\Core\Application::deactivate();
});

// 4. Hook into plugins_loaded to check requirements & boot application
add_action('plugins_loaded', function () {
    if (!\Arma\ArmaPanel\Core\Requirements::check()) {
        \Arma\ArmaPanel\Core\Requirements::registerNotices();
        return;
    }

    \Arma\ArmaPanel\Core\Application::getInstance()->boot();
}, 10);
