<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @var array<string, mixed> $user
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var array<string, mixed> $section_data
 */

$isSingle = (bool) ($section_data['is_single'] ?? false);
$ticketsBaseUrl = function_exists('arma_panel_url') ? arma_panel_url('tickets') : home_url('/' . trim((string)$settings->get('general.panel_slug', 'arma-panel'), '/') . '/tickets/');
?>

<?php if ($isSingle): ?>
    <?php
    $details = $section_data['details'] ?? null;
    if (!$details || empty($details['ticket'])):
    ?>
        <div class="arma-card">
            <div class="arma-empty">
                <p><?php esc_html_e('تیکت مورد نظر یافت نشد یا شما به آن دسترسی ندارید.', 'arma-panel'); ?></p>
                <a href="<?php echo esc_url($ticketsBaseUrl); ?>" class="arma-btn arma-btn--outline">
                    <?php esc_html_e('بازگشت به لیست تیکت‌ها', 'arma-panel'); ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php
        $ticket   = $details['ticket'];
        $messages = $details['messages'] ?? [];
        $isPending = ($ticket->status === 'pending');
        $statusLabel = $isPending ? __('در حال بررسی', 'arma-panel') : __('پاسخ داده شده', 'arma-panel');
        $statusClass = $isPending ? 'arma-badge--pending' : 'arma-badge--replied';
        ?>

        <div class="arma-card">
            <div class="arma-card-header">
                <h2 class="arma-card-header__title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span><?php echo esc_html($ticket->subject); ?></span>
                </h2>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="arma-badge <?php echo esc_attr($statusClass); ?>">
                        <?php echo esc_html($statusLabel); ?>
                    </span>
                    <a href="<?php echo esc_url($ticketsBaseUrl); ?>" class="arma-btn arma-btn--outline arma-btn--sm">
                        <?php esc_html_e('بازگشت', 'arma-panel'); ?>
                    </a>
                </div>
            </div>

            <!-- Messages Thread -->
            <div style="padding: 24px; display: flex; flex-direction: column; gap: 16px;">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $isAdmin = ($msg->sender_type === 'admin');
                    $dateStr = function_exists('arma_to_persian_digits') ? arma_to_persian_digits($msg->created_at) : $msg->created_at;
                    ?>
                    <div style="padding: 16px; border-radius: var(--arma-radius-md); background: <?php echo $isAdmin ? '#f0fdf4' : '#f8fafc'; ?>; border: 1px solid <?php echo $isAdmin ? '#bbf7d0' : 'var(--arma-border)'; ?>; max-width: 85%; <?php echo $isAdmin ? 'margin-left: auto; margin-right: 0;' : 'margin-right: 0;'; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.8rem; color: var(--arma-text-muted);">
                            <strong>
                                <?php if ($isAdmin): ?>
                                    <span style="color: var(--arma-success);"><?php esc_html_e('پشتیبان سایت', 'arma-panel'); ?></span>
                                <?php else: ?>
                                    <span><?php echo esc_html($user['display_name']); ?></span>
                                <?php endif; ?>
                            </strong>
                            <span style="direction: ltr;"><?php echo esc_html($dateStr); ?></span>
                        </div>
                        <div style="font-size: 0.92rem; line-height: 1.7; color: var(--arma-text);">
                            <?php echo nl2br(esc_html($msg->message)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Reply Box -->
            <div style="padding: 20px; border-top: 1px solid var(--arma-border); background: #ffffff;">
                <h4 style="margin: 0 0 12px; font-size: 0.95rem; font-weight: 700;"><?php esc_html_e('ارسال پاسخ', 'arma-panel'); ?></h4>
                <div id="arma-ticket-reply-msg" style="display: none; padding: 10px; margin-bottom: 12px; border-radius: 6px; font-size: 0.85rem;"></div>

                <form id="arma-reply-ticket-form">
                    <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket->id; ?>">
                    <div class="arma-form-group">
                        <textarea name="message" class="arma-form-control" rows="4" placeholder="<?php esc_attr_e('پاسخ خود را اینجا بنویسید...', 'arma-panel'); ?>" required></textarea>
                    </div>
                    <button type="submit" class="arma-btn arma-btn--primary">
                        <?php esc_html_e('ارسال پاسخ', 'arma-panel'); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Ticket Listing & New Ticket Form -->
    <div class="arma-card" style="margin-bottom: 24px;">
        <div class="arma-card-header">
            <h2 class="arma-card-header__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span><?php esc_html_e('ارسال تیکت جدید', 'arma-panel'); ?></span>
            </h2>
        </div>
        <div style="padding: 20px;">
            <div id="arma-ticket-form-msg" style="display: none; padding: 10px; margin-bottom: 12px; border-radius: 6px; font-size: 0.85rem;"></div>

            <form id="arma-new-ticket-form">
                <div class="arma-form-group">
                    <label class="arma-form-label" for="ticket_subject"><?php esc_html_e('موضوع تیکت', 'arma-panel'); ?></label>
                    <input type="text" id="ticket_subject" name="subject" class="arma-form-control" placeholder="<?php esc_attr_e('مثال: سوال درباره سفارش قبلی', 'arma-panel'); ?>" required>
                </div>
                <div class="arma-form-group">
                    <label class="arma-form-label" for="ticket_message"><?php esc_html_e('متن پیام', 'arma-panel'); ?></label>
                    <textarea id="ticket_message" name="message" class="arma-form-control" rows="4" placeholder="<?php esc_attr_e('شرح کامل درخواست خود را بیان کنید...', 'arma-panel'); ?>" required></textarea>
                </div>
                <button type="submit" class="arma-btn arma-btn--primary">
                    <?php esc_html_e('ثبت و ارسال تیکت', 'arma-panel'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Tickets List -->
    <div class="arma-card">
        <div class="arma-card-header">
            <h2 class="arma-card-header__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span><?php esc_html_e('تیکت‌های پشتیبانی من', 'arma-panel'); ?></span>
            </h2>
        </div>

        <div class="arma-order-list">
            <?php
            $tickets = $section_data['tickets'] ?? [];
            if (!empty($tickets)):
                foreach ($tickets as $t):
                    $isPending = ($t->status === 'pending');
                    $statusLabel = $isPending ? __('در حال بررسی', 'arma-panel') : __('پاسخ داده شده', 'arma-panel');
                    $statusClass = $isPending ? 'arma-badge--pending' : 'arma-badge--replied';
                    $dateFormatted = function_exists('arma_to_persian_digits') ? arma_to_persian_digits($t->updated_at) : $t->updated_at;
            ?>
                <a href="<?php echo esc_url($ticketsBaseUrl . $t->id . '/'); ?>" class="arma-order-item">
                    <div class="arma-order-item__right">
                        <div class="arma-order-item__thumb">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div class="arma-order-item__info">
                            <span class="arma-order-item__title"><?php echo esc_html($t->subject); ?></span>
                            <span class="arma-order-item__date"><?php printf(esc_html__('آخرین بروزرسانی: %s', 'arma-panel'), esc_html($dateFormatted)); ?></span>
                        </div>
                    </div>

                    <div class="arma-order-item__left">
                        <span class="arma-badge <?php echo esc_attr($statusClass); ?>">
                            <?php echo esc_html($statusLabel); ?>
                        </span>
                    </div>
                </a>
            <?php
                endforeach;
            else:
            ?>
                <div class="arma-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <p><?php esc_html_e('شما هنوز هیچ تیکت پشتیبانی ایجاد نکرده‌اید.', 'arma-panel'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
