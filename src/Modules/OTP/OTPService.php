<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\OTP;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\SMS\SMSManager;
use Arma\ArmaPanel\Support\Phone\PhoneService;

/**
 * OTP Service orchestrating generation, dispatching, and verification.
 */
class OTPService {

    private OTPGenerator $generator;
    private OTPRepository $repository;
    private OTPRateLimiter $rateLimiter;
    private OTPVerifier $verifier;
    private SettingsManager $settings;
    private PhoneService $phoneService;
    private SMSManager $smsManager;

    public function __construct(
        OTPGenerator $generator,
        OTPRepository $repository,
        OTPRateLimiter $rateLimiter,
        OTPVerifier $verifier,
        SettingsManager $settings,
        PhoneService $phoneService,
        SMSManager $smsManager
    ) {
        $this->generator    = $generator;
        $this->repository   = $repository;
        $this->rateLimiter  = $rateLimiter;
        $this->verifier     = $verifier;
        $this->settings     = $settings;
        $this->phoneService = $phoneService;
        $this->smsManager   = $smsManager;
    }

    /**
     * Generate and send an OTP code via SMS.
     *
     * @param string $phone
     * @param string $purpose 'login' or 'register'
     * @return array{success: bool, message: string, cooldown?: int, expires_in?: int}
     */
    public function sendOtp(string $phone, string $purpose = 'login'): array {
        $canonical = $this->phoneService->normalize($phone);

        if (!$this->phoneService->validate($canonical)) {
            return [
                'success' => false,
                'message' => __('شماره موبایل وارد شده معتبر نمی‌باشد.', 'arma-panel'),
            ];
        }

        // Global IP throttle — BEFORE any generation/SMS (protects SMS credit
        // against attackers cycling random phone numbers to bypass per-phone cooldown).
        $trustProxy = (bool) $this->settings->get('authentication.trusted_proxy', false);
        $clientIp   = OTPRateLimiter::getClientIp($trustProxy);
        $ipMax      = (int) $this->settings->get('authentication.ip_rate_max', 5);
        $ipWindow   = (int) $this->settings->get('authentication.ip_rate_window', 600);

        if ($ipMax > 0 && $this->rateLimiter->isIpRateLimited($clientIp, $ipMax, $ipWindow)) {
            $this->logRateLimit('ip_rate_limited', $clientIp, $canonical);

            return [
                'success' => false,
                'message' => __('تعداد درخواست‌های ارسال کد از این شبکه بیش از حد مجاز است؛ لطفاً کمی بعد دوباره تلاش کنید.', 'arma-panel'),
            ];
        }

        $resendDelay = (int) $this->settings->get('authentication.otp_resend_delay', 60);
        $cooldown = $this->rateLimiter->getResendCooldown($canonical, $resendDelay);

        // Also honour the no-record send marker (OTP-disabled accounts).
        $cooldown = max($cooldown, $this->getMarkerCooldown($canonical, $resendDelay));

        if ($cooldown > 0) {
            $this->logRateLimit('phone_rate_limited', $clientIp, $canonical);

            return [
                'success'  => false,
                'message'  => sprintf(
                    /* translators: %d: remaining seconds */
                    __('لطفاً %d ثانیه دیگر مجدداً تلاش نمایید.', 'arma-panel'),
                    $cooldown
                ),
                'cooldown' => $cooldown,
            ];
        }

        $otpLength = (int) $this->settings->get('authentication.otp_length', 5);
        $expiry    = (int) $this->settings->get('authentication.otp_expiry', 120);

        // 1. Generate code and hash
        $plainCode = $this->generator->generate($otpLength);
        $otpHash   = $this->generator->hash($plainCode);

        // 2. Save in database
        $ip = $clientIp;
        $id = $this->repository->create($canonical, $otpHash, $purpose, $expiry, $ip);

        if ($id <= 0) {
            return [
                'success' => false,
                'message' => __('خطا در ثبت کد تایید در سیستم. لطفاً دوباره تلاش کنید.', 'arma-panel'),
            ];
        }

        // 3. Send SMS via Melipayamak pattern
        $smsSent = $this->smsManager->sendOtp($canonical, $plainCode);

        if (!$smsSent->isSuccessful()) {
            return [
                'success' => false,
                'message' => $smsSent->getMessage() ?: __('خطا در ارسال پیامک کد تایید. لطفاً با پشتیبانی تماس بگیرید.', 'arma-panel'),
            ];
        }

        return [
            'success'    => true,
            'message'    => __('کد تایید با موفقیت ارسال شد.', 'arma-panel'),
            'cooldown'   => $resendDelay,
            'expires_in' => $expiry,
        ];
    }

    /**
     * Verify an OTP code.
     *
     * @param string $phone
     * @param string $code
     * @param string $purpose
     * @return array{success: bool, message: string}
     */
    public function verifyOtp(string $phone, string $code, string $purpose = 'login'): array {
        $canonical = $this->phoneService->normalize($phone);
        $maxAttempts = (int) $this->settings->get('authentication.max_attempts', 5);

        return $this->verifier->verify($canonical, $code, $purpose, $maxAttempts);
    }

    /**
     * Run ONLY the send-side throttles (per-IP cap + per-phone resend
     * cooldown) WITHOUT creating an OTP record or sending anything.
     *
     * Used for accounts that must never receive a code: from the outside the
     * request behaves exactly like a real send (throttles consumed, cooldown
     * window started) so the two flows are indistinguishable.
     *
     * @param string $phone
     * @return array{success: bool, message: string, cooldown?: int}
     */
    public function consumeSendThrottle(string $phone): array {
        $canonical = $this->phoneService->normalize($phone);

        if (!$this->phoneService->validate($canonical)) {
            return [
                'success' => false,
                'message' => __('شماره موبایل وارد شده معتبر نمی‌باشد.', 'arma-panel'),
            ];
        }

        // Same per-IP throttle a real send runs through (records the request).
        $trustProxy = (bool) $this->settings->get('authentication.trusted_proxy', false);
        $clientIp   = OTPRateLimiter::getClientIp($trustProxy);
        $ipMax      = (int) $this->settings->get('authentication.ip_rate_max', 5);
        $ipWindow   = (int) $this->settings->get('authentication.ip_rate_window', 600);

        if ($ipMax > 0 && $this->rateLimiter->isIpRateLimited($clientIp, $ipMax, $ipWindow)) {
            $this->logRateLimit('ip_rate_limited', $clientIp, $canonical);

            return [
                'success' => false,
                'message' => __('تعداد درخواست‌های ارسال کد از این شبکه بیش از حد مجاز است؛ لطفاً کمی بعد دوباره تلاش کنید.', 'arma-panel'),
            ];
        }

        // Same per-phone cooldown a real send enforces (record-based or marker).
        $resendDelay = (int) $this->settings->get('authentication.otp_resend_delay', 60);
        $cooldown = max(
            $this->rateLimiter->getResendCooldown($canonical, $resendDelay),
            $this->getMarkerCooldown($canonical, $resendDelay)
        );

        if ($cooldown > 0) {
            $this->logRateLimit('phone_rate_limited', $clientIp, $canonical);

            return [
                'success'  => false,
                'message'  => sprintf(
                    /* translators: %d: remaining seconds */
                    __('لطفاً %d ثانیه دیگر مجدداً تلاش نمایید.', 'arma-panel'),
                    $cooldown
                ),
                'cooldown' => $cooldown,
            ];
        }

        // Start the cooldown window WITHOUT creating a record: a lightweight
        // send marker transient plays the role of the OTP row.
        if (function_exists('set_transient') && $resendDelay > 0) {
            set_transient('arma_otp_sent_' . md5($canonical), time(), $resendDelay);
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    /**
     * Remaining cooldown from the no-record send marker (if any).
     */
    private function getMarkerCooldown(string $phone, int $delay): int {
        if ($delay <= 0 || !function_exists('get_transient')) {
            return 0;
        }

        $sentAt = get_transient('arma_otp_sent_' . md5($phone));

        if (!is_numeric($sentAt)) {
            return 0;
        }

        $elapsed = time() - (int) $sentAt;

        return $elapsed < $delay ? $delay - $elapsed : 0;
    }

    /**
     * Leave a DECOY OTP record for a number that must never receive a real
     * code (privileged accounts): the row carries the hash of a long random
     * secret that no SMS ever delivers, so the VERIFY side behaves exactly
     * like a real send — the same "wrong code, N attempts left" message, the
     * same attempts limit, the same expiry — instead of answering "no
     * record" and leaking the number's privileged status through a single
     * wrong guess.
     *
     * No SMS is dispatched, ever, and no code the user could type ever
     * matches the stored hash (the secret is 64 random characters, not a
     * numeric OTP).
     */
    public function issueDecoyOtp(string $phone): bool {
        $canonical = $this->phoneService->normalize($phone);

        if (!$this->phoneService->validate($canonical)) {
            return false;
        }

        $secret = function_exists('wp_generate_password')
            ? wp_generate_password(64, false)
            : bin2hex(random_bytes(32));

        $expiry    = (int) $this->settings->get('authentication.otp_expiry', 120);
        $trustProxy = (bool) $this->settings->get('authentication.trusted_proxy', false);

        $id = $this->repository->create(
            $canonical,
            $this->generator->hash($secret),
            'login',
            $expiry,
            OTPRateLimiter::getClientIp($trustProxy)
        );

        return $id > 0;
    }

    public function getRepository(): OTPRepository {
        return $this->repository;
    }

    /**
     * Structured rate-limit logging so IP-based denials are distinguishable
     * from per-phone cooldowns in debug logs.
     */
    private function logRateLimit(string $reason, string $ip, string $phone): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[ArmaPanel OTP] %s ip=%s phone=%s', $reason, $ip, $this->maskPhone($phone)));
        }
    }

    private function maskPhone(string $phone): string {
        return strlen($phone) >= 7 ? substr($phone, 0, 4) . str_repeat('*', 3) . substr($phone, -2) : '***';
    }
}

