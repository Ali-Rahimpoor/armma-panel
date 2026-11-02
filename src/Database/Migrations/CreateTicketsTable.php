<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database\Migrations;

use wpdb;

class CreateTicketsTable implements MigrationInterface {

    public function getVersion(): string {
        return '1.0.0';
    }

    public function up(wpdb $wpdb): void {
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'arma_tickets';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            subject varchar(255) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY updated_at (updated_at)
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
        $table_name = $wpdb->prefix . 'arma_tickets';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
