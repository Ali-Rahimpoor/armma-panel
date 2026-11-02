<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Tickets;

use wpdb;

/**
 * Data Access Repository for Ticket Messages (wp_arma_ticket_messages).
 */
class TicketMessageRepository {

    private wpdb $db;
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'arma_ticket_messages';
    }

    /**
     * Store a new message in a ticket thread.
     *
     * @param int $ticketId
     * @param int $userId
     * @param string $senderType 'customer' or 'admin'
     * @param string $message
     * @return int Inserted ID
     */
    public function create(int $ticketId, int $userId, string $senderType, string $message): int {
        $now = current_time('mysql');

        $inserted = $this->db->insert(
            $this->table,
            [
                'ticket_id'   => $ticketId,
                'user_id'     => $userId,
                'sender_type' => $senderType,
                'message'     => $message,
                'created_at'  => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );

        return $inserted !== false ? (int) $this->db->insert_id : 0;
    }

    /**
     * Get all messages for a ticket in chronological order.
     *
     * @param int $ticketId
     * @return array<object>
     */
    public function getByTicket(int $ticketId): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE ticket_id = %d ORDER BY created_at ASC",
                $ticketId
            )
        ) ?: [];
    }

    /**
     * Delete all messages for a ticket.
     *
     * @param int $ticketId
     */
    public function deleteByTicket(int $ticketId): void {
        $this->db->delete($this->table, ['ticket_id' => $ticketId], ['%d']);
    }

    /**
     * Count orphaned messages whose ticket no longer exists.
     *
     * @return int
     */
    public function countOrphan(): int {
        $ticketsTable = $this->db->prefix . 'arma_tickets';
        return (int) $this->db->get_var(
            "SELECT COUNT(m.id) FROM {$this->table} m
             LEFT JOIN {$ticketsTable} t ON m.ticket_id = t.id
             WHERE t.id IS NULL"
        );
    }

    /**
     * Batch delete orphaned messages.
     *
     * @param int $limit
     * @return int
     */
    /**
     * Batch delete orphan ticket messages (whose ticket no longer exists).
     *
     * two-step SELECT-then-DELETE — the multi-table
     * DELETE ... FROM ... LEFT JOIN ... LIMIT syntax is not supported by all
     * MySQL/MariaDB configurations, and an unbounded IN() list is a DoS risk.
     * IDs are selected with a LIMIT, cast to int, and deleted in one chunk.
     */
    public function deleteOrphan(int $limit = 200): int {
        $ticketsTable = $this->db->prefix . 'arma_tickets';

        // 1. Identify orphan IDs (bounded by the limit).
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT m.id FROM {$this->table} m
                 LEFT JOIN {$ticketsTable} t ON m.ticket_id = t.id
                 WHERE t.id IS NULL
                 LIMIT %d",
                $limit
            )
        );

        if (!is_array($rows) || $rows === []) {
            return 0;
        }

        // 2. Delete exactly those IDs — every value is a hard-cast int, so
        //    the IN() list is always a safe literal list.
        $ids = array_map('intval', array_column($rows, 'id'));
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $deleted = (int) $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} WHERE id IN ({$placeholders})",
                ...$ids
            )
        );

        return $deleted;
    }
}
