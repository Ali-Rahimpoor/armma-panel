<?php
/**
 * Arma Panel Uninstall
 *
 * Triggered only when the plugin is uninstalled by WordPress.
 *
 * Data removal is OPT-IN: unless the admin explicitly ticked the
 * «حذف کامل داده‌ها هنگام حذف افزونه» checkbox in Settings → General, the
 * uninstaller only clears EPHEMERAL data (transients, spin locks, cron
 * events) — tables, options, user meta and order meta are left in place
 * (safe for a reinstall or a later inspection).
 *
 * With the opt-in enabled, the full cleanup runs —
 *  - multisite-aware (loops every site of the network);
 *  - drops every custom table, including arma_notification_reads;
 *  - deletes ALL plugin user meta (arma_phone, wishlist, avatar, reminders);
 *  - deletes order meta from BOTH the classic postmeta table and the HPOS
 *    wc_orders_meta table (when HPOS is active);
 *  - HPOS-safe: no post-meta assumption is made about orders.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Run the cleanup for ONE site (the current blog).
 */
function arma_panel_uninstall_site(): void {
    global $wpdb;

    /*
     * Read the RAW saved settings BEFORE touching anything: the decision
     * must come from what is stored in the DB, not from runtime defaults.
     */
    $savedSettings = get_option('arma_settings', []);
    $deleteAllData = is_array($savedSettings)
        && !empty($savedSettings['general']['delete_data_on_uninstall']);

    // ==================================================================
    // ALWAYS — ephemeral data only (no user data is touched)
    // ==================================================================

    // 1. Transients: VALUE rows and TIMEOUT rows.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_arma_') . '%',
            $wpdb->esc_like('_transient_timeout_arma_') . '%'
        )
    );

    // 2. Per-order spin locks (short-lived lock rows in wp_options).
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('arma_wheel_lock_') . '%'
        )
    );

    // 3. The autoload-upgrade marker: plugin bookkeeping, carries no data
    //    (and deleting it lets a reinstall re-run the one-time upgrade).
    delete_option('arma_panel_autoload_migrated');

    // 4. Scheduled cron hooks.
    wp_clear_scheduled_hook('arma_panel_abandoned_check');

    if (!$deleteAllData) {
        // Panel rewrites disappear with the plugin either way.
        flush_rewrite_rules();

        return;
    }

    // ==================================================================
    // OPT-IN — full data removal
    // ==================================================================

    // 5. Drop custom tables (including the read-receipts table)
    $tables = [
        $wpdb->prefix . 'arma_notification_reads',
        $wpdb->prefix . 'arma_otps',
        $wpdb->prefix . 'arma_ticket_messages',
        $wpdb->prefix . 'arma_tickets',
        $wpdb->prefix . 'arma_notifications',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    // 6. Delete plugin options
    $options = [
        'arma_settings',
        'arma_db_version',
        'arma_panel_rewrite_hash',
        'arma_panel_slug_conflict',
        'arma_panel_page_id',
    ];

    // Delete the auto-created customer panel page
    $panelPageId = (int) get_option('arma_panel_page_id', 0);
    if ($panelPageId > 0 && function_exists('wp_delete_post')) {
        wp_delete_post($panelPageId, true);
    }

    foreach ($options as $option) {
        delete_option($option);
    }

    // 7. Delete plugin-specific USER meta
    $user_meta_keys = [
        'arma_phone',
        'arma_phone_verified',
        'arma_national_id',
        'arma_job',
        'arma_refund_method',
        'arma_avatar',
        '_arma_wishlist',
        '_arma_abandoned_reminded',
        '_arma_cart_active_at',
        '_arma_cart_reminded',
    ];

    foreach ($user_meta_keys as $meta_key) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            )
        );
    }

    // 8. Remove plugin ORDER meta — HPOS-safe.
    //    Classic stores keep order meta in postmeta; HPOS stores keep it in
    //    the wc_orders_meta table. Both are cleaned, whichever exists.
    $order_meta_keys = [
        '_arma_wheel_token',
        '_arma_wheel_spun',
        '_arma_wheel_user',
        '_arma_wheel_coupon',
        '_arma_wheel_prize',
        '_arma_tracking_number',
        '_arma_tracking_company',
        '_arma_order_sms_sent',
        '_arma_pending_reminded',
    ];

    // 8a. Classic (posts-based) order storage.
    foreach ($order_meta_keys as $meta_key) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            )
        );
    }

    // 8b. HPOS custom order tables: wp_wc_orders_meta (table exists only
    //     when HPOS was ever active on the store).
    $hposMetaTable = $wpdb->prefix . 'wc_orders_meta';

    $hposTableExists = (int) $wpdb->query(
        $wpdb->prepare('SHOW TABLES LIKE %s', $hposMetaTable)
    ) > 0;

    if ($hposTableExists) {
        foreach ($order_meta_keys as $meta_key) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$hposMetaTable} WHERE meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL
                    $meta_key
                )
            );
        }
    }

    // 9. Personal prize coupons: remove the Arma ownership marker meta so
    //    nothing half-plugin lingers on kept coupons.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            '_arma_user_id'
        )
    );

    // 10. Flush rewrite rules (panel rewrites disappear with the plugin).
    flush_rewrite_rules();
}

/*
 * Multisite-aware uninstall: clean EVERY site of the network, restoring the
 * current blog afterwards. The opt-in flag is evaluated PER SITE.
 */
if (function_exists('is_multisite') && is_multisite() && function_exists('get_sites')) {
    $arma_original_site_id = get_current_blog_id();

    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $arma_site_id) {
        switch_to_blog((int) $arma_site_id);
        arma_panel_uninstall_site();
    }

    switch_to_blog($arma_original_site_id);
} else {
    arma_panel_uninstall_site();
}
