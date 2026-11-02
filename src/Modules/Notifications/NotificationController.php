<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Notifications;

/**
 * Controller for notifications interactions.
 */
class NotificationController {

    private NotificationService $service;

    public function __construct(NotificationService $service) {
        $this->service = $service;
    }

    public function registerHooks(): void {
        add_action('wp_ajax_arma_mark_notification_read', [$this, 'handleMarkRead']);
        add_action('wp_ajax_arma_mark_all_notifications_read', [$this, 'handleMarkAllRead']);
    }

    public function handleMarkRead(): void {
        check_ajax_referer('arma_notification_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('لطفاً ابتدا وارد حساب خود شوید.', 'arma-panel')]);
        }

        $userId = get_current_user_id();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id > 0) {
            $this->service->markAsRead($id, $userId);
        }

        wp_send_json_success(['message' => __('علامت‌گذاری به عنوان خوانده شده انجام شد.', 'arma-panel')]);
    }

    public function handleMarkAllRead(): void {
        check_ajax_referer('arma_notification_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('لطفاً ابتدا وارد حساب خود شوید.', 'arma-panel')]);
        }

        $userId = get_current_user_id();
        $this->service->markAllAsRead($userId);

        wp_send_json_success(['message' => __('تمام اطلاعیه‌ها به عنوان خوانده شده علامت‌گذاری شدند.', 'arma-panel')]);
    }
}
