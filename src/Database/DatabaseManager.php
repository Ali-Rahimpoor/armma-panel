<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Database\Migrations\CreateNotificationReadsTable;
use Arma\ArmaPanel\Database\Migrations\CreateNotificationsTable;
use Arma\ArmaPanel\Database\Migrations\CreateOtpTable;
use Arma\ArmaPanel\Database\Migrations\CreateTicketMessagesTable;
use Arma\ArmaPanel\Database\Migrations\CreateTicketsTable;
use Arma\ArmaPanel\Database\Migrations\MigrationInterface;
use wpdb;

/**
 * Database Manager handling migrations and table lifecycle.
 */
class DatabaseManager {

    public const DB_VERSION_OPTION = 'arma_db_version';
    public const CURRENT_DB_VERSION = '1.2.0';

    /**
     * One-time marker: the wp_options autoload upgrade for the plugin's hot
     * options already ran on this installation.
     */
    public const AUTOLOAD_MIGRATED_OPTION = 'arma_panel_autoload_migrated';

    /**
     * Hot options that must be autoload=yes: they are read on (nearly)
     * EVERY request. The Lucky Wheel spin lock is deliberately NOT here —
     * it is write-only hot garbage that must stay out of alloptions.
     */
    public const AUTOLOAD_OPTIONS = [
        SettingsManager::OPTION_NAME,   // arma_settings
        self::DB_VERSION_OPTION,        // arma_db_version
        'arma_panel_page_id',
        'arma_panel_rewrite_hash',
        'arma_panel_slug_conflict',
    ];

    /**
     * Registered migrations in execution order.
     *
     * @var array<class-string<MigrationInterface>>
     */
    private array $migrations = [
        CreateOtpTable::class,
        CreateTicketsTable::class,
        CreateTicketMessagesTable::class,
        CreateNotificationsTable::class,
        // per-user read receipts for broadcast notifications.
        CreateNotificationReadsTable::class,
    ];

    /**
     * Run any pending database migrations.
     */
    public function migrate(): void {
        global $wpdb;

        $installed_version = get_option(self::DB_VERSION_OPTION, '0.0.0');

        if (version_compare((string) $installed_version, self::CURRENT_DB_VERSION, '>=')) {
            return;
        }

        foreach ($this->migrations as $migrationClass) {
            /** @var MigrationInterface $migration */
            $migration = new $migrationClass();
            $migration->up($wpdb);
        }

        $this->migrateSettingsData();

        update_option(self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION, true);
    }

    /**
     * One-time upgrade: flip the plugin's hot options to autoload=yes.
     *
     * Existing installations created these rows with autoload=no, which cost
     * one SELECT per option per request. Runs once (guarded by a tiny
     * autoloaded marker option) and then never touches the DB again. The
     * Lucky Wheel spin lock stays non-autoload on purpose.
     */
    public function migrateAutoloadFlags(): void {
        global $wpdb;

        if (get_option(self::AUTOLOAD_MIGRATED_OPTION, false)) {
            return; // already upgraded on this installation
        }

        foreach (self::AUTOLOAD_OPTIONS as $optionName) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->options} SET autoload = 'yes' WHERE option_name = %s",
                    $optionName
                )
            );
        }

        // Marker itself autoloaded (and tiny): the guard check is free.
        update_option(self::AUTOLOAD_MIGRATED_OPTION, 1, true);
    }

    /**
     * One-time settings data migrations (no schema changes).
     */
    private function migrateSettingsData(): void {
        $settings = get_option(SettingsManager::OPTION_NAME, null);

        if (!is_array($settings)) {
            return;
        }

        $changed = false;

        // v1.1.0: upgrade old default panel title to «حساب کاربری من»
        if (isset($settings['general']['panel_title'])
            && $settings['general']['panel_title'] === 'حساب کاربری'
        ) {
            $settings['general']['panel_title'] = 'حساب کاربری من';
            $changed = true;
        }

        if ($changed) {
            update_option(SettingsManager::OPTION_NAME, $settings, true);
        }
    }

    /**
     * Drop all custom plugin tables (used strictly during plugin uninstall).
     */
    public function dropAllTables(): void {
        global $wpdb;

        $tables = $this->getTableNames();
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Get list of full table names with WP prefix.
     *
     * @return array<string>
     */
    public function getTableNames(): array {
        global $wpdb;

        return [
            $wpdb->prefix . 'arma_otps',
            $wpdb->prefix . 'arma_ticket_messages',
            $wpdb->prefix . 'arma_tickets',
            $wpdb->prefix . 'arma_notifications',
            $wpdb->prefix . 'arma_notification_reads',
        ];
    }
}
