<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database\Migrations;

use wpdb;

class CreateOtpTable implements MigrationInterface {

    public function getVersion(): string {
        return '1.0.0';
    }

    public function up(wpdb $wpdb): void {
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'arma_otps';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            otp_hash varchar(255) NOT NULL,
            purpose varchar(32) NOT NULL DEFAULT 'login',
            attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY phone_purpose (phone, purpose),
            KEY expires_at (expires_at),
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
        $table_name = $wpdb->prefix . 'arma_otps';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
