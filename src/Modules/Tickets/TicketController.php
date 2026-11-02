<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Tickets;

/**
 * Frontend customer controller for tickets.
 */
class TicketController {

    private TicketService $ticketService;

    public function __construct(TicketService $ticketService) {
        $this->ticketService = $ticketService;
    }

    public function registerHooks(): void {
        add_action('wp_ajax_arma_create_ticket', [$this, 'handleCreateTicket']);
        add_action('wp_ajax_arma_reply_ticket', [$this, 'handleReplyTicket']);
    }

    public function handleCreateTicket(): void {
        check_ajax_referer('arma_ticket_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'arma-panel')]);
        }

        $userId  = get_current_user_id();
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        $result = $this->ticketService->createTicket($userId, $subject, $message);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function handleReplyTicket(): void {
        check_ajax_referer('arma_ticket_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('لطفاً ابتدا وارد حساب کاربری خود شوید.', 'arma-panel')]);
        }

        $userId   = get_current_user_id();
        $ticketId = isset($_POST['ticket_id']) ? (int) $_POST['ticket_id'] : 0;
        $message  = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        $result = $this->ticketService->replyAsCustomer($ticketId, $userId, $message);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
