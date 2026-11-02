<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\OTP;

use Arma\ArmaPanel\Core\SettingsManager;

/**
 * Validates provided OTP codes against hashed database records.
 *
 * the verification flow is now
 *   1. IP throttle + phone lockout checks (transient-backed);
 *   2. ONE atomic conditional UPDATE consuming an attempt
 *      (attempts+1 WHERE attempts < max AND used_at IS NULL) — only when
 *      rows_affected === 1 does the code comparison even happen;
 *   3. atomic markAsUsed guarded by used_at IS NULL.
 */
class OTPVerifier {

    /** Failed attempts (across all codes) that lock a phone number. */
    private const PHONE_LOCK_THRESHOLD = 15;

    /** How long a locked phone number stays locked (seconds). */
    private const PHONE_LOCK_SECONDS = 900;

    private OTPRepository $repository;
    private OTPRateLimiter $rateLimiter;
    private ?SettingsManager $settings;

    public function __construct(OTPRepository $repository, OTPRateLimiter $rateLimiter, ?SettingsManager $settings = null) {
        $this->repository   = $repository;
        $this->rateLimiter  = $rateLimiter;
        $this->settings     = $settings;
    }

    /**
     * Verify input OTP code for phone and purpose.
     *
     * @param string $phone Normalized phone.
     * @param string $code Plain user-entered code.
     * @param string $purpose 'login' or 'register'.
     * @param int $maxAttempts
     * @return array{success: bool, message: string}
     */
    public function verify(string $phone, string $code, string $purpose, int $maxAttempts = 5): array {
        // ── IP-based verify throttle (configurable, default 20/10min)
        $ipMax   = $this->setting('authentication.verify_ip_max', 20);
        $ipWindow = $this->setting('authentication.verify_ip_window', 600);
        $clientIp = OTPRateLimiter::getClientIp($this->trustProxy());

        if ($ipMax > 0 && $this->rateLimiter->isVerifyThrottled($clientIp, $ipMax, $ipWindow)) {
            return [
                'success' => false,
                'message' => __('تعداد درخواست‌های تایید از این شبکه بیش از حد مجاز است؛ لطفاً کمی بعد دوباره تلاش کنید.', 'arma-panel'),
            ];
        }

        // ── A (phone, IP) combination with too many failed attempts is locked out.
        if ($this->rateLimiter->isPhoneLocked($phone, $clientIp)) {
            return [
                'success' => false,
                'message' => __('تعداد تلاش‌های ناموفق برای این شماره بیش از حد مجاز است؛ لطفاً ۱۵ دقیقه دیگر تلاش کنید.', 'arma-panel'),
            ];
        }

        $record = $this->repository->findLatestActive($phone, $purpose);

        if (!$record) {
            /*
             * No active record: this is NOT a real failed attempt. Counting
             * it would let anyone lock a victim's number with 15 anonymous
             * requests — the per-IP throttle above already absorbs the flood,
             * so nothing is recorded here.
             */
            return [
                'success' => false,
                'message' => __('کد تایید منقضی شده یا یافت نشد. لطفاً مجدداً درخواست ارسال کد دهید.', 'arma-panel'),
            ];
        }

        if ($this->rateLimiter->isAttemptsExceeded($record, $maxAttempts)) {
            return [
                'success' => false,
                'message' => __('تعداد دفعات وارد کردن کد بیش از حد مجاز است. لطفاً کد جدید دریافت کنید.', 'arma-panel'),
            ];
        }

        /*
         * Consume the attempt FIRST, atomically. Only the request whose
         * UPDATE actually affected a row gets to compare the code — two
         * parallel guesses for the last allowed attempt cannot both win.
         */
        if (!$this->repository->tryIncrementAttempts((int) $record->id, $maxAttempts)) {
            return [
                'success' => false,
                'message' => __('تعداد دفعات وارد کردن کد بیش از حد مجاز است. لطفاً کد جدید دریافت کنید.', 'arma-panel'),
            ];
        }

        // Clean user input
        $cleanCode = trim($code);

        // Constant time / bcrypt comparison
        if (!password_verify($cleanCode, $record->otp_hash)) {
            // A REAL failed attempt (a live code existed and was wrong).
            $this->rateLimiter->recordFailedPhoneAttempt($phone, $clientIp, self::PHONE_LOCK_THRESHOLD, self::PHONE_LOCK_SECONDS);
            $remaining = max(0, $maxAttempts - ((int) $record->attempts + 1));

            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %d: remaining attempts count */
                    __('کد تایید وارد شده نادرست است. (%d تلاش باقیمانده)', 'arma-panel'),
                    $remaining
                ),
            ];
        }

        /*
         * Success: mark as used ATOMICALLY (used_at IS NULL guard) so the
         * code cannot be replayed — not even by a concurrent request.
         */
        if (!$this->repository->markAsUsed((int) $record->id)) {
            return [
                'success' => false,
                'message' => __('کد تایید قبلاً استفاده شده است. لطفاً کد جدید دریافت کنید.', 'arma-panel'),
            ];
        }

        // A successful verification forgives this (phone, IP) failure history.
        $this->rateLimiter->clearPhoneFailures($phone, $clientIp);

        return [
            'success' => true,
            'message' => __('کد تایید با موفقیت تایید شد.', 'arma-panel'),
        ];
    }

    private function setting(string $key, int $default): int {
        return $this->settings !== null ? (int) $this->settings->get($key, $default) : $default;
    }

    private function trustProxy(): bool {
        return $this->settings !== null
            && (bool) $this->settings->get('authentication.trusted_proxy', false);
    }
}
