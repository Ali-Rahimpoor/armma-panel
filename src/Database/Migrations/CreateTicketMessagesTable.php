<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database\Migrations;

use wpdb;

class CreateTicketMessagesTable implements MigrationInterface {

    public function getVersion(): string {
        return '1.0.0';
    }

    public function up(wpdb $wpdb): void {
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'arma_ticket_messages';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            sender_type varchar(20) NOT NULL DEFAULT 'customer',
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY ticket_id (ticket_id),
            KEY user_id (user_id),
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
        $table_name = $wpdb->prefix . 'arma_ticket_messages';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}
