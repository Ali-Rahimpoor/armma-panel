<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Admin\Controllers;

use Arma\ArmaPanel\Modules\Notifications\NotificationService;

/**
 * Admin controller for sending and managing notifications.
 */
class AdminNotificationController {

    private NotificationService $service;

    public function __construct(NotificationService $service) {
        $this->service = $service;
    }

    public function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما دسترسی لازم برای این بخش را ندارید.', 'arma-panel'));
        }

        // Handle creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arma_create_notification_nonce'])) {
            check_admin_referer('arma_create_notification', 'arma_create_notification_nonce');

            $title   = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
            $message = wp_kses_post(wp_unslash($_POST['message'] ?? ''));
            $target  = sanitize_text_field(wp_unslash($_POST['target'] ?? 'all'));
            $userId  = 0;

            if ($target === 'specific') {
                $userId = (int) ($_POST['user_id'] ?? 0);
            }

            if (!empty($title) && !empty($message)) {
                /*
                 * a "specific user" target must resolve to a REAL
                 * account — otherwise a typo'd ID silently created a
                 * notification nobody would ever see. Invalid target = error,
                 * nothing is sent.
                 */
                $invalidTarget = false;

                if ($target === 'specific'
                    && ($userId <= 0 || !function_exists('get_userdata') || get_userdata($userId) === false)
                ) {
                    $invalidTarget = true;
                }

                if ($invalidTarget) {
                    echo '<div class="notice notice-error is-dismissible"><p>'
                        . esc_html__('کاربر مورد نظر یافت نشد؛ اطلاعیه ارسال نشد. لطفاً شناسه کاربری معتبر وارد کنید.', 'arma-panel')
                        . '</p></div>';
                } else {
                    $this->service->create($userId, $title, $message, 'system');
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('اطلاعیه با موفقیت ارسال شد.', 'arma-panel') . '</p></div>';
                }
            }
        }

        $notifications = $this->service->getRepository()->getAll(30);
        ?>
        <div class="wrap" style="max-width: 1000px; direction: rtl; text-align: right;">
            <h1><?php esc_html_e('ارسال و مدیریت اطلاعیه‌ها', 'arma-panel'); ?></h1>

            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; margin-bottom: 24px;">
                <h2><?php esc_html_e('ارسال اطلاعیه جدید', 'arma-panel'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('arma_create_notification', 'arma_create_notification_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="notif_title"><?php esc_html_e('عنوان اطلاعیه', 'arma-panel'); ?></label></th>
                            <td><input name="title" id="notif_title" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notif_message"><?php esc_html_e('متن اطلاعیه', 'arma-panel'); ?></label></th>
                            <td><textarea name="message" id="notif_message" rows="4" class="large-text" required></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('گیرنده اطلاعیه', 'arma-panel'); ?></th>
                            <td>
                                <label><input type="radio" name="target" value="all" checked> <?php esc_html_e('همه مشتریان و کاربران (همگانی)', 'arma-panel'); ?></label><br>
                                <label><input type="radio" name="target" value="specific"> <?php esc_html_e('کاربر خاص (با شناسه کاربر)', 'arma-panel'); ?></label>
                                <input type="number" name="user_id" placeholder="<?php esc_attr_e('شناسه کاربری', 'arma-panel'); ?>" class="small-text" style="margin-right: 10px;">
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('ارسال اطلاعیه', 'arma-panel'); ?>">
                    </p>
                </form>
            </div>

            <h2><?php esc_html_e('تاریخچه اطلاعیه‌ها', 'arma-panel'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('شناسه', 'arma-panel'); ?></th>
                        <th><?php esc_html_e('عنوان', 'arma-panel'); ?></th>
                        <th><?php esc_html_e('گیرنده', 'arma-panel'); ?></th>
                        <th><?php esc_html_e('نوع', 'arma-panel'); ?></th>
                        <th style="width: 160px;"><?php esc_html_e('تاریخ ثبت', 'arma-panel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $n): ?>
                            <tr>
                                <td>#<?php echo (int) $n->id; ?></td>
                                <td><strong><?php echo esc_html($n->title); ?></strong></td>
                                <td><?php echo (int)$n->user_id === 0 ? esc_html__('همگانی (همه کاربران)', 'arma-panel') : sprintf(esc_html__('کاربر #%d', 'arma-panel'), (int)$n->user_id); ?></td>
                                <td><?php echo esc_html($n->type); ?></td>
                                <td><span style="direction: ltr; display: inline-block;"><?php echo esc_html($n->created_at); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5"><?php esc_html_e('هیچ اطلاعیه‌ای یافت نشد.', 'arma-panel'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
