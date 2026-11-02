<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Tickets;

use Arma\ArmaPanel\Modules\Notifications\NotificationService;

/**
 * Ticket Service handling business logic for ticketing system.
 */
class TicketService {

    private TicketRepository $ticketRepo;
    private TicketMessageRepository $messageRepo;
    private ?NotificationService $notificationService;

    public function __construct(
        TicketRepository $ticketRepo,
        TicketMessageRepository $messageRepo,
        ?NotificationService $notificationService = null
    ) {
        $this->ticketRepo          = $ticketRepo;
        $this->messageRepo         = $messageRepo;
        $this->notificationService = $notificationService;
    }

    public function setNotificationService(NotificationService $service): void {
        $this->notificationService = $service;
    }

    /**
     * Create a new ticket by a customer.
     *
     * @param int $userId
     * @param string $subject
     * @param string $initialMessage
     * @return array{success: bool, ticket_id?: int, message: string}
     */
    public function createTicket(int $userId, string $subject, string $initialMessage): array {
        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => __('برای ثبت تیکت باید وارد حساب خود شوید.', 'arma-panel'),
            ];
        }

        $cleanSubject = sanitize_text_field($subject);
        $cleanMessage = wp_kses_post(trim($initialMessage));

        if (empty($cleanSubject) || empty($cleanMessage)) {
            return [
                'success' => false,
                'message' => __('لطفاً موضوع و متن تیکت را وارد نمایید.', 'arma-panel'),
            ];
        }

        $ticketId = $this->ticketRepo->create($userId, $cleanSubject, 'pending');
        if ($ticketId <= 0) {
            return [
                'success' => false,
                'message' => __('خطا در ثبت تیکت. لطفاً دوباره تلاش کنید.', 'arma-panel'),
            ];
        }

        $this->messageRepo->create($ticketId, $userId, 'customer', $cleanMessage);

        do_action('arma_ticket_created', $ticketId, $userId);

        return [
            'success'   => true,
            'ticket_id' => $ticketId,
            'message'   => __('تیکت شما با موفقیت ثبت شد و در اسرع وقت پاسخ داده خواهد شد.', 'arma-panel'),
        ];
    }

    /**
     * Customer replies to an existing ticket.
     *
     * @param int $ticketId
     * @param int $userId
     * @param string $message
     * @return array{success: bool, message: string}
     */
    public function replyAsCustomer(int $ticketId, int $userId, string $message): array {
        // Strict ownership check
        $ticket = $this->ticketRepo->findForUser($ticketId, $userId);
        if (!$ticket) {
            return [
                'success' => false,
                'message' => __('تیکت مورد نظر یافت نشد یا شما دسترسی به آن ندارید.', 'arma-panel'),
            ];
        }

        $cleanMessage = wp_kses_post(trim($message));
        if (empty($cleanMessage)) {
            return [
                'success' => false,
                'message' => __('متن پاسخ نمی‌تواند خالی باشد.', 'arma-panel'),
            ];
        }

        $this->messageRepo->create($ticketId, $userId, 'customer', $cleanMessage);
        $this->ticketRepo->updateStatus($ticketId, 'pending');

        do_action('arma_ticket_replied_by_customer', $ticketId, $userId);

        return [
            'success' => true,
            'message' => __('پاسخ شما با موفقیت ارسال شد.', 'arma-panel'),
        ];
    }

    /**
     * Administrator replies to a ticket.
     *
     * @param int $ticketId
     * @param int $adminUserId
     * @param string $message
     * @return array{success: bool, message: string}
     */
    public function replyAsAdmin(int $ticketId, int $adminUserId, string $message): array {
        $ticket = $this->ticketRepo->find($ticketId);
        if (!$ticket) {
            return [
                'success' => false,
                'message' => __('تیکت یافت نشد.', 'arma-panel'),
            ];
        }

        $cleanMessage = wp_kses_post(trim($message));
        if (empty($cleanMessage)) {
            return [
                'success' => false,
                'message' => __('متن پاسخ مدیر خالی است.', 'arma-panel'),
            ];
        }

        $this->messageRepo->create($ticketId, $adminUserId, 'admin', $cleanMessage);
        $this->ticketRepo->updateStatus($ticketId, 'replied');

        // Optional notification to user
        if ($this->notificationService !== null) {
            $this->notificationService->create(
                (int) $ticket->user_id,
                __('پاسخ جدید به تیکت شما', 'arma-panel'),
                sprintf(__('به تیکت "%s" پاسخ داده شد.', 'arma-panel'), esc_html($ticket->subject)),
                'ticket'
            );
        }

        do_action('arma_ticket_replied_by_admin', $ticketId, (int) $ticket->user_id);

        return [
            'success' => true,
            'message' => __('پاسخ با موفقیت برای کاربر ارسال شد.', 'arma-panel'),
        ];
    }

    /**
     * Get ticket and its messages for customer with strict ownership check.
     *
     * @param int $ticketId
     * @param int $userId
     * @return array<string, mixed>|null
     */
    public function getTicketDetailsForCustomer(int $ticketId, int $userId): ?array {
        $ticket = $this->ticketRepo->findForUser($ticketId, $userId);
        if (!$ticket) {
            return null;
        }

        $messages = $this->messageRepo->getByTicket($ticketId);

        return [
            'ticket'   => $ticket,
            'messages' => $messages,
        ];
    }

    /**
     * Get ticket details for admin review.
     *
     * @param int $ticketId
     * @return array<string, mixed>|null
     */
    public function getTicketDetailsForAdmin(int $ticketId): ?array {
        $ticket = $this->ticketRepo->find($ticketId);
        if (!$ticket) {
            return null;
        }

        $messages = $this->messageRepo->getByTicket($ticketId);
        $user = get_user_by('id', (int) $ticket->user_id);

        return [
            'ticket'   => $ticket,
            'messages' => $messages,
            'user'     => $user,
        ];
    }

    public function getTicketRepository(): TicketRepository {
        return $this->ticketRepo;
    }

    public function getMessageRepository(): TicketMessageRepository {
        return $this->messageRepo;
    }
}
