<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Tickets;

use wpdb;

/**
 * Data Access Repository for Tickets (wp_arma_tickets).
 */
class TicketRepository {

    private wpdb $db;
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'arma_tickets';
    }

    /**
     * Create a new ticket.
     *
     * @param int $userId
     * @param string $subject
     * @param string $status 'pending' or 'replied'
     * @return int Inserted ID
     */
    public function create(int $userId, string $subject, string $status = 'pending'): int {
        $now = current_time('mysql');

        $inserted = $this->db->insert(
            $this->table,
            [
                'user_id'    => $userId,
                'subject'    => $subject,
                'status'     => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        return $inserted !== false ? (int) $this->db->insert_id : 0;
    }

    /**
     * Find ticket by ID.
     *
     * @param int $ticketId
     * @return object|null
     */
    public function find(int $ticketId): ?object {
        $row = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $ticketId)
        );

        return $row ?: null;
    }

    /**
     * Find ticket by ID strictly checking user ownership.
     *
     * @param int $ticketId
     * @param int $userId
     * @return object|null
     */
    public function findForUser(int $ticketId, int $userId): ?object {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d AND user_id = %d LIMIT 1",
                $ticketId,
                $userId
            )
        );

        return $row ?: null;
    }

    /**
     * Get tickets for a specific user.
     *
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array<object>
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $userId,
                $limit,
                $offset
            )
        ) ?: [];
    }

    /**
     * Get total tickets count for user.
     */
    public function countForUser(int $userId): int {
        return (int) $this->db->get_var(
            $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d", $userId)
        );
    }

    /**
     * Get all tickets (for admin).
     *
     * @param string|null $status
     * @param int $limit
     * @param int $offset
     * @return array<object>
     */
    public function getAll(?string $status = null, int $limit = 20, int $offset = 0): array {
        if ($status && in_array($status, ['pending', 'replied'], true)) {
            return $this->db->get_results(
                $this->db->prepare(
                    "SELECT * FROM {$this->table} WHERE status = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                    $status,
                    $limit,
                    $offset
                )
            ) ?: [];
        }

        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        ) ?: [];
    }

    /**
     * Count all tickets (for admin).
     */
    public function countAll(?string $status = null): int {
        if ($status && in_array($status, ['pending', 'replied'], true)) {
            return (int) $this->db->get_var(
                $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status)
            );
        }
        return (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    /**
     * Update ticket status and updated_at timestamp.
     *
     * @param int $ticketId
     * @param string $status
     * @return void
     */
    public function updateStatus(int $ticketId, string $status): void {
        $now = current_time('mysql');
        $this->db->update(
            $this->table,
            [
                'status'     => $status,
                'updated_at' => $now,
            ],
            ['id' => $ticketId],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Delete ticket and its messages.
     *
     * @param int $ticketId
     */
    public function delete(int $ticketId): void {
        $this->db->delete($this->table, ['id' => $ticketId], ['%d']);
    }
}
