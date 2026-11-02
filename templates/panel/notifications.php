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

$notifications = $section_data['notifications'] ?? [];
?>

<div class="arma-card">
    <div class="arma-card-header">
        <h2 class="arma-card-header__title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span><?php esc_html_e('اطلاعیه‌ها و پیام‌های سیستم', 'arma-panel'); ?></span>
        </h2>

        <?php if (!empty($notifications)): ?>
            <button type="button" id="arma-mark-all-notifications" class="arma-btn arma-btn--outline arma-btn--sm">
                <?php esc_html_e('علامت‌گذاری همه به عنوان خوانده شده', 'arma-panel'); ?>
            </button>
        <?php endif; ?>
    </div>

    <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $n): ?>
                <?php
                $isUnread = ((int) $n->is_read === 0);
                $dateFormatted = function_exists('arma_to_persian_digits') ? arma_to_persian_digits($n->created_at) : $n->created_at;
                ?>
                <div class="arma-notification-item <?php echo $isUnread ? 'arma-notification-item--unread' : ''; ?>" style="padding: 16px; border-radius: var(--arma-radius-md); border: 1px solid <?php echo $isUnread ? 'var(--arma-primary-light)' : 'var(--arma-border)'; ?>; background: <?php echo $isUnread ? '#fffafb' : '#ffffff'; ?>; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <?php if ($isUnread): ?>
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--arma-primary); display: inline-block;"></span>
                            <?php endif; ?>
                            <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--arma-heading);">
                                <?php echo esc_html($n->title); ?>
                            </h4>
                            <span style="font-size: 0.75rem; color: var(--arma-text-muted); margin-right: auto; direction: ltr;">
                                <?php echo esc_html($dateFormatted); ?>
                            </span>
                        </div>
                        <div style="font-size: 0.88rem; color: var(--arma-text); line-height: 1.6;">
                            <?php echo wp_kses_post($n->message); ?>
                        </div>
                    </div>

                    <?php if ($isUnread): ?>
                        <button type="button" class="arma-btn arma-btn--outline arma-btn--sm arma-btn-mark-notification-read" data-id="<?php echo (int) $n->id; ?>">
                            <?php esc_html_e('خوانده شد', 'arma-panel'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="arma-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                </svg>
                <p><?php esc_html_e('در حال حاضر اطلاعیه‌ای برای شما وجود ندارد.', 'arma-panel'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
