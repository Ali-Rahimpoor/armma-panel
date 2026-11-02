<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database\Migrations;

use wpdb;

/**
 * per-user read receipts for BROADCAST notifications.
 *
 * The old schema had a single is_read flag on the notification row itself,
 * so the first user who marked a broadcast "read" made it disappear as
 * unread for every other customer. Personal (user_id > 0) notifications keep
 * using is_read; broadcast (user_id = 0) reads now live in this table with
 * a composite unique key (notification_id, user_id).
 *
 * DB version 1.2.0.
 */
class CreateNotificationReadsTable implements MigrationInterface {

    public function getVersion(): string {
        return '1.2.0';
    }

    public function up(wpdb $wpdb): void {
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'arma_notification_reads';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            read_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY notification_user (notification_id, user_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        if (function_exists('dbDelta')) {
            dbDelta($sql);
        } else {
            $wpdb->query($sql);
        }
    }

    public function down(wpdb $wpdb): void {
        $table_name = $wpdb->prefix . 'arma_notification_reads';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
