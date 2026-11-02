<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database\Migrations;

use wpdb;

class CreateNotificationsTable implements MigrationInterface {

    public function getVersion(): string {
        return '1.0.0';
    }

    public function up(wpdb $wpdb): void {
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'arma_notifications';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            type varchar(32) NOT NULL DEFAULT 'system',
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY is_read (is_read),
            KEY created_at (created_at)
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
        $table_name = $wpdb->prefix . 'arma_notifications';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
