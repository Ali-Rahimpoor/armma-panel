<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Database\Migrations;

use wpdb;

interface MigrationInterface {
    /**
     * Migration version string (e.g. '1.0.0').
     */
    public function getVersion(): string;

    /**
     * Run migration up.
     */
    public function up(wpdb $wpdb): void;

    /**
     * Run migration down / rollback.
     */
    public function down(wpdb $wpdb): void;
}
