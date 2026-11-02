<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Notifications;

/**
 * Notification Service handling system, order, and ticket announcements.
 */
class NotificationService {

    private NotificationRepository $repository;

    public function __construct(NotificationRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Create a notification.
     *
     * @param int $userId 0 for all users (broadcast) or customer ID.
     * @param string $title
     * @param string $message
     * @param string $type
     * @return int
     */
    public function create(int $userId, string $title, string $message, string $type = 'system'): int {
        $cleanTitle   = sanitize_text_field($title);
        $cleanMessage = wp_kses_post($message);

        return $this->repository->create($userId, $cleanTitle, $cleanMessage, $type);
    }

    /**
     * Get notifications for a user.
     *
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array<object>
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array {
        if ($userId <= 0) {
            return [];
        }
        return $this->repository->getForUser($userId, $limit, $offset);
    }

    /**
     * Get unread notifications count for sidebar badge.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int {
        if ($userId <= 0) {
            return 0;
        }
        return $this->repository->countUnreadForUser($userId);
    }

    /**
     * Mark single notification as read.
     *
     * @param int $id
     * @param int $userId
     */
    public function markAsRead(int $id, int $userId): void {
        $this->repository->markAsRead($id, $userId);
    }

    /**
     * Mark all notifications for user as read.
     *
     * @param int $userId
     */
    public function markAllAsRead(int $userId): void {
        $this->repository->markAllAsRead($userId);
    }

    public function getRepository(): NotificationRepository {
        return $this->repository;
    }
}
