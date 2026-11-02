<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Admin\Controllers;

use Arma\ArmaPanel\Modules\Tickets\TicketService;

/**
 * Admin controller for managing support tickets.
 */
class AdminTicketController {

    private TicketService $ticketService;

    public function __construct(TicketService $ticketService) {
        $this->ticketService = $ticketService;
    }

    public function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما دسترسی لازم برای این بخش را ندارید.', 'arma-panel'));
        }

        $ticketId = isset($_GET['ticket_id']) ? (int) $_GET['ticket_id'] : 0;

        // Handle Admin Reply submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arma_admin_reply_nonce'])) {
            check_admin_referer('arma_admin_reply', 'arma_admin_reply_nonce');

            $tId     = (int) ($_POST['ticket_id'] ?? 0);
            $message = sanitize_textarea_field(wp_unslash($_POST['reply_message'] ?? ''));

            if ($tId > 0 && !empty($message)) {
                $this->ticketService->replyAsAdmin($tId, get_current_user_id(), $message);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('پاسخ با موفقیت برای مشتری ارسال شد.', 'arma-panel') . '</p></div>';
            }
        }

        if ($ticketId > 0) {
            $this->renderSingleTicket($ticketId);
        } else {
            $this->renderTicketsList();
        }
    }

    /**
     * Unanswered (pending) ticket count — feeds the wp-admin menu badge
     * so the admin instantly sees new customer tickets.
     */
    public function getPendingTicketCount(): int {
        try {
            return (int) $this->ticketService->getTicketRepository()->countAll('pending');
        } catch (\Throwable $e) {
            return 0; // tables not created yet / DB hiccup: never break the menu
        }
    }

    private function renderTicketsList(): void {
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : null;
        $tickets = $this->ticketService->getTicketRepository()->getAll($status, 50);
        $totalPending = $this->ticketService->getTicketRepository()->countAll('pending');
        $totalReplied = $this->ticketService->getTicketRepository()->countAll('replied');
        $totalAll     = $this->ticketService->getTicketRepository()->countAll();
        ?>
        <div class="wrap" style="direction: rtl; text-align: right;">
            <h1><?php esc_html_e('مدیریت تیکت‌های پشتیبانی', 'arma-panel'); ?></h1>

            <ul class="subsubsub" style="margin-bottom: 16px;">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=arma-panel-tickets')); ?>" class="<?php echo empty($status) ? 'current' : ''; ?>"><?php printf(esc_html__('همه (%d)', 'arma-panel'), $totalAll); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=arma-panel-tickets&status=pending')); ?>" class="<?php echo $status === 'pending' ? 'current' : ''; ?>"><?php printf(esc_html__('در انتظار بررسی (%d)', 'arma-panel'), $totalPending); ?></a> |</li>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=arma-panel-tickets&status=replied')); ?>" class="<?php echo $status === 'replied' ? 'current' : ''; ?>"><?php printf(esc_html__('پاسخ داده شده (%d)', 'arma-panel'), $totalReplied); ?></a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('شناسه', 'arma-panel'); ?></th>
                        <th><?php esc_html_e('موضوع تیکت', 'arma-panel'); ?></th>
                        <th><?php esc_html_e('مشتری (کاربر)', 'arma-panel'); ?></th>
                        <th style="width: 130px;"><?php esc_html_e('وضعیت', 'arma-panel'); ?></th>
                        <th style="width: 160px;"><?php esc_html_e('آخرین بروزرسانی', 'arma-panel'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('عملیات', 'arma-panel'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $t): ?>
                            <?php
                            $user = get_user_by('id', (int) $t->user_id);
                            $userName = $user ? ($user->display_name ?: $user->user_login) : sprintf(__('کاربر #%d', 'arma-panel'), (int)$t->user_id);
                            $isPending = ($t->status === 'pending');
                            ?>
                            <tr>
                                <td>#<?php echo (int) $t->id; ?></td>
                                <td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=arma-panel-tickets&ticket_id=' . (int)$t->id)); ?>"><?php echo esc_html($t->subject); ?></a></strong></td>
                                <td><?php echo esc_html($userName); ?></td>
                                <td>
                                    <?php if ($isPending): ?>
                                        <span style="color: #d97706; font-weight: 600;"><?php esc_html_e('در حال بررسی', 'arma-panel'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #059669; font-weight: 600;"><?php esc_html_e('پاسخ داده شده', 'arma-panel'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span style="direction: ltr; display: inline-block;"><?php echo esc_html($t->updated_at); ?></span></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=arma-panel-tickets&ticket_id=' . (int)$t->id)); ?>" class="button button-small">
                                        <?php esc_html_e('مشاهده و پاسخ', 'arma-panel'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><?php esc_html_e('هیچ تیکتی یافت نشد.', 'arma-panel'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function renderSingleTicket(int $ticketId): void {
        $details = $this->ticketService->getTicketDetailsForAdmin($ticketId);
        if (!$details) {
            echo '<div class="wrap"><p>' . esc_html__('تیکت یافت نشد.', 'arma-panel') . '</p></div>';
            return;
        }

        $ticket   = $details['ticket'];
        $messages = $details['messages'];
        $user     = $details['user'];
        $userName = $user ? ($user->display_name ?: $user->user_login) : sprintf(__('کاربر #%d', 'arma-panel'), (int)$ticket->user_id);
        ?>
        <div class="wrap" style="max-width: 900px; direction: rtl; text-align: right;">
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=arma-panel-tickets')); ?>">&larr; <?php esc_html_e('بازگشت به لیست تیکت‌ها', 'arma-panel'); ?></a></p>
            <h1><?php printf(esc_html__('تیکت #%d: %s', 'arma-panel'), (int)$ticket->id, esc_html($ticket->subject)); ?></h1>
            <p><?php printf(esc_html__('ارسال شده توسط: %s | وضعیت: %s', 'arma-panel'), '<strong>' . esc_html($userName) . '</strong>', ($ticket->status === 'pending' ? '<span style="color:#d97706;font-weight:700;">در حال بررسی</span>' : '<span style="color:#059669;font-weight:700;">پاسخ داده شده</span>')); ?></p>

            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 6px; margin: 20px 0; display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $isAdmin = ($msg->sender_type === 'admin');
                    ?>
                    <div style="padding: 14px; border-radius: 6px; background: <?php echo $isAdmin ? '#f0fdf4' : '#f8fafc'; ?>; border: 1px solid <?php echo $isAdmin ? '#bbf7d0' : '#e2e8f0'; ?>;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem;">
                            <strong><?php echo $isAdmin ? esc_html__('پاسخ مدیر', 'arma-panel') : esc_html($userName); ?></strong>
                            <span style="color: #666; direction: ltr;"><?php echo esc_html($msg->created_at); ?></span>
                        </div>
                        <div style="line-height: 1.6;"><?php echo nl2br(esc_html($msg->message)); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply Form -->
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 6px;">
                <h3><?php esc_html_e('ارسال پاسخ به این تیکت', 'arma-panel'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('arma_admin_reply', 'arma_admin_reply_nonce'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                    <textarea name="reply_message" rows="5" class="large-text" placeholder="<?php esc_attr_e('متن پاسخ مدیر...', 'arma-panel'); ?>" required></textarea>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('ارسال پاسخ', 'arma-panel'); ?>">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
