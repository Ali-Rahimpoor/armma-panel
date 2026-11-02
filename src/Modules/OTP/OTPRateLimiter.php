<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\OTP;

/**
 * Rate Limiter for OTP generation and verification attempts.
 */
class OTPRateLimiter {

    private OTPRepository $repository;

    public function __construct(OTPRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Check if a phone number is currently in cooldown period for new OTP requests.
     *
     * @param string $phone
     * @param int $cooldownSeconds
     * @return int Remaining seconds to wait (0 if ready to send).
     */
    public function getResendCooldown(string $phone, int $cooldownSeconds = 60): int {
        $latest = $this->repository->findLatest($phone);
        if (!$latest) {
            return 0;
        }

        $createdAt = strtotime($latest->created_at . ' GMT');
        $elapsed = time() - $createdAt;

        if ($elapsed < $cooldownSeconds) {
            return $cooldownSeconds - $elapsed;
        }

        return 0;
    }

    /**
     * Check if OTP attempts exceeded maximum allowed.
     *
     * @param object $otpRecord
     * @param int $maxAttempts
     * @return bool
     */
    public function isAttemptsExceeded(object $otpRecord, int $maxAttempts = 5): bool {
        return (int) $otpRecord->attempts >= $maxAttempts;
    }

    /**
     * Global IP-based OTP request throttle (sliding window, stored in a transient).
     *
     * Protects the shop's SMS credit: an attacker spamming random phone numbers
     * (never repeating one, so the per-phone cooldown never triggers) is still
     * capped at $max requests per $window seconds per IP.
     *
     * @param string $ip
     * @param int $maxRequests  Max OTP requests allowed in the window.
     * @param int $windowSeconds Sliding window length in seconds.
     * @return bool TRUE when the IP exceeded the limit (do NOT send).
     */
    public function isIpRateLimited(string $ip, int $maxRequests = 5, int $windowSeconds = 600): bool {
        if ($ip === '' || $maxRequests <= 0 || !function_exists('get_transient') || !function_exists('set_transient')) {
            return false; // never block legitimate flows when transients are unavailable
        }

        $key     = 'arma_otp_ip_' . md5($ip);
        $now     = time();
        $history = get_transient($key);
        $history = is_array($history) ? $history : [];

        // Drop timestamps outside the sliding window
        $history = array_values(array_filter($history, static fn($ts) => is_numeric($ts) && ($now - (int) $ts) < $windowSeconds));

        if (count($history) >= $maxRequests) {
            return true; // over the limit — nothing recorded, the window rolls on
        }

        $history[] = $now;
        set_transient($key, $history, $windowSeconds);

        return false;
    }

    /**
     * Verify-endpoint IP throttle.
     *
     * Distinct from the send-throttle above (separate key namespace):
     * every verification attempt is recorded in a sliding window; once the
     * window holds $max attempts the IP is blocked until it rolls over.
     * Defaults are admin-configurable (20 attempts / 10 minutes).
     *
     * @return bool TRUE when the IP is currently over the verify limit.
     */
    public function isVerifyThrottled(string $ip, int $max = 20, int $windowSeconds = 600): bool {
        if ($ip === '' || $max <= 0 || !function_exists('get_transient') || !function_exists('set_transient')) {
            return false; // never block legitimate flows when transients are unavailable
        }

        $key     = 'arma_vrfy_ip_' . md5($ip);
        $now     = time();
        $history = get_transient($key);
        $history = is_array($history) ? $history : [];

        // Drop timestamps outside the sliding window
        $history = array_values(array_filter($history, static fn($ts) => is_numeric($ts) && ($now - (int) $ts) < $windowSeconds));

        if (count($history) >= $max) {
            return true; // over the limit — nothing recorded, the window rolls on
        }

        $history[] = $now;
        set_transient($key, $history, $windowSeconds);

        return false;
    }

    /**
     * Lock/failure keys are bound to the COMBINATION of phone number and
     * client IP: an attacker hammering a number from their own IP only
     * locks themselves out — the real owner (different IP) keeps verifying.
     */
    private function phoneLockKey(string $phone, string $ip): string {
        return 'arma_phone_lock_' . md5($phone . '|' . $ip);
    }

    private function phoneFailKey(string $phone, string $ip): string {
        return 'arma_phone_fail_' . md5($phone . '|' . $ip);
    }

    /**
     * Is this (phone, IP) combination currently locked out of verification?
     * (15 failed attempts across all codes → 15-minute lock.)
     */
    public function isPhoneLocked(string $phone, string $ip, int $lockSeconds = 900): bool {
        if ($phone === '' || $ip === '' || !function_exists('get_transient')) {
            return false;
        }

        return get_transient($this->phoneLockKey($phone, $ip)) !== false;
    }

    /**
     * Record one failed verification attempt for a (phone, IP) combination.
     * Once $threshold failures accumulate inside the window, that
     * combination is locked for $lockSeconds and the failure history is
     * reset.
     */
    public function recordFailedPhoneAttempt(string $phone, string $ip, int $threshold = 15, int $windowSeconds = 900, int $lockSeconds = 900): void {
        if ($phone === '' || $ip === '' || $threshold <= 0 || !function_exists('get_transient') || !function_exists('set_transient')) {
            return;
        }

        $key     = $this->phoneFailKey($phone, $ip);
        $now     = time();
        $history = get_transient($key);
        $history = is_array($history) ? $history : [];

        $history = array_values(array_filter($history, static fn($ts) => is_numeric($ts) && ($now - (int) $ts) < $windowSeconds));
        $history[] = $now;

        if (count($history) >= $threshold) {
            // Lock the combination and reset the failure history so the lock
            // is not instantly re-armed after it expires.
            set_transient($this->phoneLockKey($phone, $ip), $now, $lockSeconds);
            delete_transient($key);

            return;
        }

        set_transient($key, $history, $windowSeconds);
    }

    /**
     * Forgive the failure history of a (phone, IP) combination after a
     * successful verification.
     */
    public function clearPhoneFailures(string $phone, string $ip): void {
        if ($phone !== '' && $ip !== '' && function_exists('delete_transient')) {
            delete_transient($this->phoneFailKey($phone, $ip));
        }
    }

    /**
     * Get the client IP address safely.
     *
     * Forwarded headers (X-Forwarded-For / CF-Connecting-IP) are attacker-spoofable
     * unless the site really sits behind a trusted reverse proxy/CDN that sets them.
     * They are therefore honoured ONLY when $trustProxyHeaders is enabled by the
     * admin ("site behind a trusted proxy"); otherwise REMOTE_ADDR is authoritative.
     *
     * @param bool $trustProxyHeaders
     * @return string
     */
    public static function getClientIp(bool $trustProxyHeaders = false): string {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($trustProxyHeaders) {
            // Cloudflare sets the connecting IP directly
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $candidate = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    $ip = $candidate;
                }
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $candidate = trim($parts[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    $ip = $candidate;
                }
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }
}
