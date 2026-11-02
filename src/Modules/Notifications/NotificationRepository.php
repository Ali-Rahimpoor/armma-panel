<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Notifications;

use wpdb;

/**
 * Data Access Repository for Notifications (wp_arma_notifications).
 *
 * BROADCAST notifications (user_id = 0) no longer share one
 * global is_read flag — each user's read state lives in the new
 * wp_arma_notification_reads table (composite unique notification_id+user_id).
 * PERSONAL notifications (user_id > 0) keep using the row's own is_read flag.
 */
class NotificationRepository {

    private wpdb $db;
    private string $table;
    private string $readsTable;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'arma_notifications';
        $this->readsTable = $wpdb->prefix . 'arma_notification_reads';
    }

    /**
     * Create a notification.
     *
     * @param int $userId 0 for all users, or specific user ID.
     * @param string $title
     * @param string $message
     * @param string $type 'system', 'order', 'ticket'
     * @return int Inserted ID
     */
    public function create(int $userId, string $title, string $message, string $type = 'system'): int {
        $now = current_time('mysql');

        $inserted = $this->db->insert(
            $this->table,
            [
                'user_id'    => $userId,
                'title'      => $title,
                'message'    => $message,
                'type'       => $type,
                'is_read'    => 0,
                'created_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );

        return $inserted !== false ? (int) $this->db->insert_id : 0;
    }

    /**
     * Get notifications for a user: their personal ones plus broadcasts,
     * each row carrying ITS OWN read state for THIS user.
     *
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array<object>
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array {
        /*
         * is_read semantics:
         *  - personal row  -> the row's own is_read flag
         *  - broadcast row -> 1 only when THIS user has a read receipt
         *  - legacy broadcast already flagged is_read = 1 -> read for
         *    everyone (the old global flag), so it can never pop up as
         *    unread again
         *  - broadcast that PREDATES the account registration -> read:
         *    someone who registered yesterday should not be badge-spammed
         *    by months-old announcements
         */
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT n.*,
                        CASE
                            WHEN n.user_id = %d THEN n.is_read
                            WHEN n.is_read = 1 THEN 1
                            WHEN r.notification_id IS NOT NULL THEN 1
                            WHEN n.created_at < %s THEN 1
                            ELSE 0
                        END AS is_read
                 FROM {$this->table} n
                 LEFT JOIN {$this->readsTable} r
                    ON r.notification_id = n.id AND r.user_id = %d
                 WHERE n.user_id = %d OR n.user_id = 0
                 ORDER BY n.created_at DESC
                 LIMIT %d OFFSET %d",
                $userId,
                $this->userRegisteredAt($userId),
                $userId,
                $userId,
                $limit,
                $offset
            )
        ) ?: [];
    }

    /**
     * Count unread notifications for a user: unread personal rows plus
     * broadcast rows without a read receipt from THIS user. Broadcasts that
     * predate the account registration (or were already flagged read the
     * legacy way) never count as unread.
     *
     * @param int $userId
     * @return int
     */
    public function countUnreadForUser(int $userId): int {
        return (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*)
                 FROM {$this->table} n
                 LEFT JOIN {$this->readsTable} r
                    ON r.notification_id = n.id AND r.user_id = %d
                 WHERE (n.user_id = %d AND n.is_read = 0)
                    OR (n.user_id = 0
                        AND n.is_read = 0
                        AND r.notification_id IS NULL
                        AND n.created_at >= %s)",
                $userId,
                $userId,
                $this->userRegisteredAt($userId)
            )
        );
    }

    /**
     * The account's registration date (SITE time, Y-m-d H:i:s) — broadcasts
     * older than this are treated as already read for that account.
     * Returns '0000-00-00 00:00:00' when unknown (comparison stays neutral).
     */
    private function userRegisteredAt(int $userId): string {
        if ($userId <= 0 || !function_exists('get_userdata')) {
            return '0000-00-00 00:00:00';
        }

        $user = get_userdata($userId);

        if ($user && isset($user->user_registered) && (string) $user->user_registered !== '') {
            /*
             * user_registered is stored UTC while created_at rows are written
             * with current_time('mysql') (site-local). Convert the stamp to
             * site time so the comparison runs on ONE clock — otherwise
             * broadcasts from the UTC-offset window just before the
             * registration would wrongly count as unread.
             */
            if (function_exists('get_date_from_gmt')) {
                return (string) get_date_from_gmt((string) $user->user_registered);
            }

            return (string) $user->user_registered;
        }

        return '0000-00-00 00:00:00';
    }

    /**
     * Mark a single notification as read FOR THIS USER.
     *
     * Broadcasts get a per-user read receipt; personal rows flip their own
     * is_read flag (only when the row belongs to this user).
     *
     * @param int $id
     * @param int $userId
     * @return void
     */
    public function markAsRead(int $id, int $userId): void {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT id, user_id FROM {$this->table} WHERE id = %d AND (user_id = %d OR user_id = 0)",
                $id,
                $userId
            )
        );

        if (!$row) {
            return;
        }

        if ((int) $row->user_id === 0) {
            // Broadcast: per-user receipt (INSERT IGNORE — idempotent).
            $this->db->query(
                $this->db->prepare(
                    "INSERT IGNORE INTO {$this->readsTable} (notification_id, user_id, read_at)
                     VALUES (%d, %d, %s)",
                    $id,
                    $userId,
                    current_time('mysql')
                )
            );

            return;
        }

        // Personal row: only its owner may flip the shared flag.
        $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table} SET is_read = 1 WHERE id = %d AND user_id = %d",
                $id,
                $userId
            )
        );
    }

    /**
     * Mark all of THIS user's notifications as read — never anyone else's.
     *
     * @param int $userId
     * @return void
     */
    public function markAllAsRead(int $userId): void {
        // 1. Personal rows of this user.
        $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table} SET is_read = 1 WHERE user_id = %d",
                $userId
            )
        );

        // 2. Receipts for every broadcast notification (INSERT IGNORE).
        $this->db->query(
            $this->db->prepare(
                "INSERT IGNORE INTO {$this->readsTable} (notification_id, user_id, read_at)
                 SELECT id, %d, %s FROM {$this->table} WHERE user_id = 0",
                $userId,
                current_time('mysql')
            )
        );
    }

    /**
     * Count old notifications (older than given days, e.g. 30 days).
     *
     * @param int $days
     * @return int
     */
    public function countOld(int $days = 30): int {
        // Site-local time: created_at is written with current_time('mysql'),
        // so the cutoff must be computed in the SAME clock (wp_date), not UTC.
        $cutoff = $this->oldCutoff($days);
        return (int) $this->db->get_var(
            $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE created_at < %s", $cutoff)
        );
    }

    /**
     * Batch delete old notifications.
     *
     * @param int $days
     * @param int $limit
     * @return int
     */
    public function deleteOld(int $days = 30, int $limit = 200): int {
        // Site-local time — see countOld().
        $cutoff = $this->oldCutoff($days);
        $deleted = (int) $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} WHERE created_at < %s LIMIT %d",
                $cutoff,
                $limit
            )
        );

        // Read receipts of deleted notifications must not linger.
        if ($deleted > 0) {
            $this->db->query(
                "DELETE r FROM {$this->readsTable} r
                 LEFT JOIN {$this->table} n ON n.id = r.notification_id
                 WHERE n.id IS NULL"
            );
        }

        return $deleted;
    }

    /**
     * Cutoff timestamp for "older than N days" queries, in SITE time.
     *
     * created_at rows are stored via current_time('mysql') (site timezone);
     * wp_date() converts back on the same clock. gmdate() would drift by the
     * site's UTC offset and purge rows up to hours too early/late.
     */
    private function oldCutoff(int $days): string {
        $timestamp = time() - ($days * 86400);

        return function_exists('wp_date')
            ? wp_date('Y-m-d H:i:s', $timestamp)
            : gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Get all notifications for admin view.
     *
     * @param int $limit
     * @param int $offset
     * @return array<object>
     */
    public function getAll(int $limit = 20, int $offset = 0): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        ) ?: [];
    }

    public function countAll(): int {
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table}");
    }
}
