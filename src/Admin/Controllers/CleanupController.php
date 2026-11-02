<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Admin\Controllers;

use Arma\ArmaPanel\Modules\Notifications\NotificationRepository;
use Arma\ArmaPanel\Modules\OTP\OTPRepository;
use Arma\ArmaPanel\Modules\Tickets\TicketMessageRepository;

/**
 * Controller handling manual database cleanup and orphan record purging.
 */
class CleanupController {

    private OTPRepository $otpRepo;
    private TicketMessageRepository $ticketMessageRepo;
    private NotificationRepository $notificationRepo;

    public function __construct(
        OTPRepository $otpRepo,
        TicketMessageRepository $ticketMessageRepo,
        NotificationRepository $notificationRepo
    ) {
        $this->otpRepo           = $otpRepo;
        $this->ticketMessageRepo = $ticketMessageRepo;
        $this->notificationRepo  = $notificationRepo;
    }

    public function renderPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما دسترسی لازم برای این بخش را ندارید.', 'arma-panel'));
        }

        $resultMessage = '';

        // Handle Cleanup Actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arma_cleanup_nonce'])) {
            check_admin_referer('arma_run_cleanup', 'arma_cleanup_nonce');

            $action = sanitize_text_field(wp_unslash($_POST['cleanup_action'] ?? ''));
            $batchSize = max(50, min(500, (int) ($_POST['batch_size'] ?? 200)));

            switch ($action) {
                case 'expired_otps':
                    $deleted = $this->otpRepo->deleteExpired($batchSize);
                    $resultMessage = sprintf(
                        /* translators: %d: count */
                        esc_html__('تعداد %d کد تایید منقضی‌شده با موفقیت پاک‌سازی شد.', 'arma-panel'),
                        $deleted
                    );
                    break;

                case 'used_otps':
                    $deleted = $this->otpRepo->deleteUsed($batchSize);
                    $resultMessage = sprintf(
                        /* translators: %d: count */
                        esc_html__('تعداد %d کد تایید مصرف‌شده با موفقیت پاک‌سازی شد.', 'arma-panel'),
                        $deleted
                    );
                    break;

                case 'old_notifications':
                    $deleted = $this->notificationRepo->deleteOld(30, $batchSize);
                    $resultMessage = sprintf(
                        /* translators: %d: count */
                        esc_html__('تعداد %d اطلاعیه قدیمی (بیش از ۳۰ روز) پاک‌سازی شد.', 'arma-panel'),
                        $deleted
                    );
                    break;

                case 'orphan_ticket_messages':
                    $deleted = $this->ticketMessageRepo->deleteOrphan($batchSize);
                    $resultMessage = sprintf(
                        /* translators: %d: count */
                        esc_html__('تعداد %d پیام تیکت بدون والد (Orphan) با موفقیت پاک‌سازی شد.', 'arma-panel'),
                        $deleted
                    );
                    break;

                case 'stale_transients':
                    global $wpdb;
                    /*
                     * Step 1 — expired transients: walk the _transient_timeout_arma_*
                     * rows already in the past. delete_transient() removes BOTH
                     * rows (value + timeout); if it fails (object-cache-backed
                     * site, glitch, …) the fallback deletes the option rows
                     * directly so a broken row can never be re-selected on
                     * every pass forever.
                     */
                    $deleted = 0;

                    for ($pass = 0; $pass < 20; $pass++) {
                        $rows = $wpdb->get_col(
                            $wpdb->prepare(
                                "SELECT option_name FROM {$wpdb->options}
                                 WHERE option_name LIKE %s AND option_value < %d
                                 LIMIT %d",
                                $wpdb->esc_like('_transient_timeout_arma_') . '%',
                                time(),
                                $batchSize
                            )
                        );

                        if (!is_array($rows) || $rows === []) {
                            break;
                        }

                        foreach ($rows as $timeoutOption) {
                            $transient = substr((string) $timeoutOption, strlen('_transient_timeout_'));

                            // Defensive: only ever arma_* transients.
                            if ($transient === '' || strpos($transient, 'arma_') !== 0) {
                                continue;
                            }

                            if (function_exists('delete_transient') && delete_transient($transient)) {
                                $deleted++;
                                continue;
                            }

                            // Fallback: purge both option rows by hand.
                            if (function_exists('delete_option')) {
                                delete_option('_transient_timeout_' . $transient);
                                delete_option('_transient_' . $transient);
                                $deleted++;
                            }
                        }
                    }

                    /*
                     * Step 2 — orphan VALUE rows: a _transient_arma_* entry
                     * whose _transient_timeout_arma_* partner is gone. Every
                     * transient this plugin creates HAS an expiry, so a lone
                     * value row is leftover garbage (e.g. a half-failed
                     * delete) that would otherwise sit in wp_options forever.
                     * Same bounded-batch pattern as above.
                     */
                    if (function_exists('delete_option')) {
                        for ($pass = 0; $pass < 20; $pass++) {
                            $orphans = $wpdb->get_col(
                                $wpdb->prepare(
                                    "SELECT o.option_name FROM {$wpdb->options} o
                                     LEFT JOIN {$wpdb->options} t
                                        ON t.option_name = CONCAT('_transient_timeout_', SUBSTRING(o.option_name, 12))
                                     WHERE o.option_name LIKE %s AND t.option_id IS NULL
                                     LIMIT %d",
                                    $wpdb->esc_like('_transient_arma_') . '%',
                                    $batchSize
                                )
                            );

                            if (!is_array($orphans) || $orphans === []) {
                                break;
                            }

                            foreach ($orphans as $orphan) {
                                if (delete_option((string) $orphan)) {
                                    $deleted++;
                                }
                            }
                        }
                    }

                    $resultMessage = sprintf(
                        /* translators: %d: count */
                        esc_html__('تعداد %d داده موقت منقضی‌شده آرما پنل پاک‌سازی شد.', 'arma-panel'),
                        $deleted
                    );
                    break;
            }
        }

        // Fetch counts
        $expiredOtpsCount   = $this->otpRepo->countExpired();
        $usedOtpsCount      = $this->otpRepo->countUsed();
        $oldNotifsCount     = $this->notificationRepo->countOld(30);
        $orphanMsgsCount    = $this->ticketMessageRepo->countOrphan();
        ?>
        <div class="wrap" style="max-width: 950px; direction: rtl; text-align: right;">
            <h1><?php esc_html_e('ابزارها و پاک‌سازی پایگاه داده (Arma Cleanup)', 'arma-panel'); ?></h1>
            <p class="description">
                <?php esc_html_e('این ابزار به شما کمک می‌کند تا رکوردهای منقضی‌شده، مصرف‌شده و بی‌استفاده افزونه آرما پنل را بدون آسیب به اطلاعات اصلی وردپرس و ووکامرس پاک‌سازی کنید.', 'arma-panel'); ?>
            </p>

            <?php if (!empty($resultMessage)): ?>
                <div class="notice notice-success is-dismissible" style="margin: 16px 0;">
                    <p><?php echo esc_html($resultMessage); ?></p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr; gap: 20px;">
                <!-- Card 1: Expired OTPs -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 6px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('کدهای تایید (OTP) منقضی‌شده', 'arma-panel'); ?></h3>
                    <p><?php printf(esc_html__('تعداد رکوردهای منقضی در پایگاه داده: %s', 'arma-panel'), '<strong>' . (int)$expiredOtpsCount . '</strong>'); ?></p>
                    <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('آیا از پاک‌سازی رکوردهای منقضی‌شده اطمینان دارید؟', 'arma-panel')); ?>');">
                        <?php wp_nonce_field('arma_run_cleanup', 'arma_cleanup_nonce'); ?>
                        <input type="hidden" name="cleanup_action" value="expired_otps">
                        <label><?php esc_html_e('تعداد در هر مرحله (Batch):', 'arma-panel'); ?>
                            <select name="batch_size">
                                <option value="100">۱۰۰</option>
                                <option value="200" selected>۲۰۰</option>
                                <option value="500">۵۰۰</option>
                            </select>
                        </label>
                        <button type="submit" class="button button-secondary" <?php echo $expiredOtpsCount === 0 ? 'disabled' : ''; ?>>
                            <?php esc_html_e('پاک‌سازی کدهای منقضی', 'arma-panel'); ?>
                        </button>
                    </form>
                </div>

                <!-- Card 2: Used OTPs -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 6px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('کدهای تایید (OTP) مصرف‌شده', 'arma-panel'); ?></h3>
                    <p><?php printf(esc_html__('تعداد رکوردهای مصرف‌شده در پایگاه داده: %s', 'arma-panel'), '<strong>' . (int)$usedOtpsCount . '</strong>'); ?></p>
                    <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('آیا از پاک‌سازی رکوردهای مصرف‌شده اطمینان دارید؟', 'arma-panel')); ?>');">
                        <?php wp_nonce_field('arma_run_cleanup', 'arma_cleanup_nonce'); ?>
                        <input type="hidden" name="cleanup_action" value="used_otps">
                        <label><?php esc_html_e('تعداد در هر مرحله (Batch):', 'arma-panel'); ?>
                            <select name="batch_size">
                                <option value="100">۱۰۰</option>
                                <option value="200" selected>۲۰۰</option>
                                <option value="500">۵۰۰</option>
                            </select>
                        </label>
                        <button type="submit" class="button button-secondary" <?php echo $usedOtpsCount === 0 ? 'disabled' : ''; ?>>
                            <?php esc_html_e('پاک‌سازی کدهای مصرف‌شده', 'arma-panel'); ?>
                        </button>
                    </form>
                </div>

                <!-- Card 3: Old Notifications -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 6px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('اطلاعیه‌های قدیمی (بیش از ۳۰ روز)', 'arma-panel'); ?></h3>
                    <p><?php printf(esc_html__('تعداد اطلاعیه‌های قدیمی‌تر از ۳۰ روز: %s', 'arma-panel'), '<strong>' . (int)$oldNotifsCount . '</strong>'); ?></p>
                    <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('آیا از پاک‌سازی اطلاعیه‌های قدیمی اطمینان دارید؟', 'arma-panel')); ?>');">
                        <?php wp_nonce_field('arma_run_cleanup', 'arma_cleanup_nonce'); ?>
                        <input type="hidden" name="cleanup_action" value="old_notifications">
                        <label><?php esc_html_e('تعداد در هر مرحله (Batch):', 'arma-panel'); ?>
                            <select name="batch_size">
                                <option value="100">۱۰۰</option>
                                <option value="200" selected>۲۰۰</option>
                                <option value="500">۵۰۰</option>
                            </select>
                        </label>
                        <button type="submit" class="button button-secondary" <?php echo $oldNotifsCount === 0 ? 'disabled' : ''; ?>>
                            <?php esc_html_e('پاک‌سازی اطلاعیه‌های قدیمی', 'arma-panel'); ?>
                        </button>
                    </form>
                </div>

                <!-- Card 4: Orphan Ticket Messages -->
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 6px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('پیام‌های تیکت بدون والد (Orphan Messages)', 'arma-panel'); ?></h3>
                    <p><?php printf(esc_html__('تعداد پیام‌های تیکت که تیکت اصلی آن‌ها در سیستم حذف شده است: %s', 'arma-panel'), '<strong>' . (int)$orphanMsgsCount . '</strong>'); ?></p>
                    <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('آیا از پاک‌سازی پیام‌های بدون والد اطمینان دارید؟', 'arma-panel')); ?>');">
                        <?php wp_nonce_field('arma_run_cleanup', 'arma_cleanup_nonce'); ?>
                        <input type="hidden" name="cleanup_action" value="orphan_ticket_messages">
                        <label><?php esc_html_e('تعداد در هر مرحله (Batch):', 'arma-panel'); ?>
                            <select name="batch_size">
                                <option value="100">۱۰۰</option>
                                <option value="200" selected>۲۰۰</option>
                                <option value="500">۵۰۰</option>
                            </select>
                        </label>
                        <button type="submit" class="button button-secondary" <?php echo $orphanMsgsCount === 0 ? 'disabled' : ''; ?>>
                            <?php esc_html_e('پاک‌سازی پیام‌های یتیم', 'arma-panel'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
