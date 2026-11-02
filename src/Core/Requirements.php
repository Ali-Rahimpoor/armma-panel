<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Core;

/**
 * Requirements checker for Arma Panel.
 * Validates PHP version, WordPress version, and WooCommerce dependency.
 */
class Requirements {

    public const MIN_PHP_VERSION = '8.0.0';
    public const MIN_WP_VERSION = '5.8';
    public const MIN_WC_VERSION = '6.0.0';

    /**
     * Check if all system requirements are met.
     */
    public static function check(): bool {
        return self::isPhpCompatible()
            && self::isWpCompatible()
            && self::isWooCommerceActive()
            && self::isWcCompatible();
    }

    /**
     * Check PHP version.
     */
    public static function isPhpCompatible(): bool {
        return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
    }

    /**
     * Check WordPress version.
     */
    public static function isWpCompatible(): bool {
        global $wp_version;
        return version_compare((string) $wp_version, self::MIN_WP_VERSION, '>=');
    }

    /**
     * Check if WooCommerce is installed and active.
     */
    public static function isWooCommerceActive(): bool {
        // Check active plugins list or WooCommerce class
        if (class_exists('WooCommerce')) {
            return true;
        }

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php');
    }

    /**
     * Check WooCommerce minimum version.
     */
    public static function isWcCompatible(): bool {
        if (!defined('WC_VERSION')) {
            return true;
        }
        return version_compare(WC_VERSION, self::MIN_WC_VERSION, '>=');
    }

    /**
     * Register admin notices for unmet requirements.
     */
    public static function registerNotices(): void {
        add_action('admin_notices', [self::class, 'renderNotices']);
    }

    /**
     * Render admin notice HTML.
     */
    public static function renderNotices(): void {
        if (!self::isPhpCompatible()) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    esc_html__('افزونه آرما پنل نیازمند نسخه PHP حداقل %1$s است. نسخه فعلی سرور شما %2$s می‌باشد.', 'arma-panel'),
                    esc_html(self::MIN_PHP_VERSION),
                    esc_html(PHP_VERSION)
                )
            );
        }

        if (!self::isWpCompatible()) {
            global $wp_version;
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required WP version, 2: Current WP version */
                    esc_html__('افزونه آرما پنل نیازمند نسخه وردپرس حداقل %1$s است. نسخه فعلی شما %2$s می‌باشد.', 'arma-panel'),
                    esc_html(self::MIN_WP_VERSION),
                    esc_html((string) $wp_version)
                )
            );
        }

        if (!self::isWooCommerceActive()) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('افزونه آرما پنل برای کارکرد صحیح نیازمند فعال بودن ووکامرس (WooCommerce) است. لطفاً ابتدا افزونه ووکامرس را نصب و فعال نمایید.', 'arma-panel')
            );
        } elseif (!self::isWcCompatible()) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Required WC version, 2: Current WC version */
                    esc_html__('افزونه آرما پنل نیازمند نسخه ووکامرس حداقل %1$s است. لطفاً ووکامرس را بروزرسانی نمایید.', 'arma-panel'),
                    esc_html(self::MIN_WC_VERSION)
                )
            );
        }
    }
}
