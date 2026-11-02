<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\OTP;

use wpdb;

/**
 * Data access repository for OTP records in wp_arma_otps.
 */
class OTPRepository {

    private wpdb $db;
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'arma_otps';
    }

    /**
     * Store a new OTP record with hashed value.
     *
     * @param string $phone Normalized phone number.
     * @param string $otpHash Hashed OTP.
     * @param string $purpose 'login' or 'register'.
     * @param int $expirySeconds Duration in seconds.
     * @param string|null $ip Client IP.
     * @return int Inserted ID or 0 on failure.
     */
    public function create(string $phone, string $otpHash, string $purpose, int $expirySeconds, ?string $ip = null): int {
        $now = current_time('mysql', true);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $expirySeconds);

        $inserted = $this->db->insert(
            $this->table,
            [
                'phone'      => $phone,
                'otp_hash'   => $otpHash,
                'purpose'    => $purpose,
                'attempts'   => 0,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'used_at'    => null,
                'ip_address' => $ip ? substr($ip, 0, 45) : null,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', null, '%s']
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Find latest active, unused, unexpired OTP for a phone number and purpose.
     *
     * @param string $phone
     * @param string $purpose
     * @return object|null
     */
    public function findLatestActive(string $phone, string $purpose): ?object {
        $now = gmdate('Y-m-d H:i:s');

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE phone = %s
                   AND purpose = %s
                   AND used_at IS NULL
                   AND expires_at > %s
                 ORDER BY id DESC
                 LIMIT 1",
                $phone,
                $purpose,
                $now
            )
        );

        return $row ?: null;
    }

    /**
     * Find latest OTP created for phone (used for resend cooldown check).
     *
     * @param string $phone
     * @return object|null
     */
    public function findLatest(string $phone): ?object {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE phone = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $phone
            )
        );

        return $row ?: null;
    }

    /**
     * Atomically consume one verification attempt for an OTP record.
     *
     * the increment and the "attempts < max" check happen in ONE
     * conditional UPDATE — two parallel requests can never both win the last
     * attempt, and a used record can never be retried at all.
     *
     * @param int $id
     * @param int $maxAttempts
     * @return bool TRUE when the attempt was consumed (rows_affected === 1).
     */
    public function tryIncrementAttempts(int $id, int $maxAttempts): bool {
        $updated = $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table}
                 SET attempts = attempts + 1
                 WHERE id = %d
                   AND attempts < %d
                   AND used_at IS NULL",
                $id,
                $maxAttempts
            )
        );

        return (int) $updated === 1;
    }

    /**
     * Atomically mark an OTP record as used.
     *
     * guarded by "used_at IS NULL" so a concurrently consumed
     * code can never be re-used; returns whether THIS call won the race.
     *
     * @param int $id
     * @return bool TRUE when the record was marked used by this call.
     */
    public function markAsUsed(int $id): bool {
        $now = current_time('mysql', true);
        $updated = $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table}
                 SET used_at = %s
                 WHERE id = %d
                   AND used_at IS NULL",
                $now,
                $id
            )
        );

        return (int) $updated === 1;
    }

    /**
     * Count expired OTP records.
     *
     * @return int
     */
    public function countExpired(): int {
        $now = gmdate('Y-m-d H:i:s');
        return (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE expires_at <= %s",
                $now
            )
        );
    }

    /**
     * Count used OTP records.
     *
     * @return int
     */
    public function countUsed(): int {
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table} WHERE used_at IS NOT NULL");
    }

    /**
     * Batch delete expired OTP records.
     *
     * @param int $limit
     * @return int Number of deleted rows.
     */
    public function deleteExpired(int $limit = 200): int {
        $now = gmdate('Y-m-d H:i:s');
        return (int) $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} WHERE expires_at <= %s LIMIT %d",
                $now,
                $limit
            )
        );
    }

    /**
     * Batch delete used OTP records.
     *
     * @param int $limit
     * @return int Number of deleted rows.
     */
    public function deleteUsed(int $limit = 200): int {
        return (int) $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} WHERE used_at IS NOT NULL LIMIT %d",
                $limit
            )
        );
    }
}
