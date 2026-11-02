<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Authentication;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\OTP\OTPService;
use Arma\ArmaPanel\Support\Phone\PhoneService;
use WP_Error;
use WP_User;

/**
 * Authentication Service handling phone lookup, internal email generation,
 * OTP authentication, registration, and user session management.
 */
class AuthService {

    /** Transient prefix for "phone verified for registration" markers. */
    private const VERIFY_TRANSIENT_PREFIX = 'arma_phone_verified_';

    /** HttpOnly cookie carrying the OTP session token. */
    public const SESSION_COOKIE = 'arma_otp_session';

    /** How long a verified phone may stay "pending registration" (seconds). */
    private const VERIFY_TTL = 600;

    /**
     * Capabilities that make an account PRIVILEGED: OTP login (and thus the
     * whole phone-based auth flow) must NEVER grant access to such accounts
     *.
     */
    private const PRIVILEGED_CAPS = [
        'manage_options',
        'manage_woocommerce',
        'edit_others_posts',
        'edit_shop_orders',
    ];

    /** Roles that are always considered privileged regardless of settings. */
    private const PRIVILEGED_ROLES = [
        'administrator',
        'shop_manager',
        'editor',
    ];

    private PhoneService $phoneService;
    private OTPService $otpService;
    private SettingsManager $settings;

    public function __construct(
        PhoneService $phoneService,
        OTPService $otpService,
        SettingsManager $settings
    ) {
        $this->phoneService = $phoneService;
        $this->otpService   = $otpService;
        $this->settings     = $settings;
    }

    /**
     * May this account be accessed through the OTP (phone) login flow?
     *
     * privileged accounts (administrators, shop managers, editors
     * — anything holding a site/shop-management capability) are hard-blocked,
     * and additionally the account must hold one of the admin-configured
     * "OTP allowed roles" (default: customer + subscriber).
     */
    public function isOtpLoginAllowed(?WP_User $user): bool {
        if (!$user instanceof WP_User) {
            return false;
        }

        // 1. Hard block: privileged capabilities/roles, no matter the settings.
        foreach (self::PRIVILEGED_CAPS as $cap) {
            if (method_exists($user, 'has_cap') && $user->has_cap($cap)) {
                return false;
            }
        }

        $roles = [];
        if (property_exists($user, 'roles') && is_array($user->roles)) {
            $roles = $user->roles;
        } elseif (isset($user->roles) && is_array($user->roles)) {
            $roles = $user->roles;
        }

        foreach (self::PRIVILEGED_ROLES as $role) {
            if (in_array($role, $roles, true)) {
                return false;
            }
        }

        // 2. Allowlist: at least one of the account's roles must be enabled
        //    in the "OTP allowed roles" setting (default customer+subscriber).
        $allowedRoles = (array) $this->settings->get('authentication.otp_allowed_roles', ['customer', 'subscriber']);
        $allowedRoles = array_values(array_filter(array_map('strval', $allowedRoles)));

        if ($allowedRoles === []) {
            return false; // empty allowlist = OTP login disabled for everyone
        }

        foreach ($roles as $role) {
            if (in_array((string) $role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send OTP for phone-based login or registration.
     *
     * when the number belongs to a privileged account, NO OTP is
     * sent at all and a generic (role-enumeration-safe) message is returned.
     *
     * @param string $phone
     * @return array{success: bool, message: string, is_registered?: bool, cooldown?: int, expires_in?: int}
     */
    public function requestOtp(string $phone): array {
        $canonical = $this->phoneService->normalize($phone);

        if (!$this->phoneService->validate($canonical)) {
            return [
                'success' => false,
                'message' => __('شماره موبایل وارد شده معتبر نمی‌باشد. لطفاً یک شماره موبایل معتبر ایران وارد کنید.', 'arma-panel'),
            ];
        }

        $existingUser = $this->phoneService->findUser($canonical);
        $isRegistered = ($existingUser instanceof WP_User);

        /*
         * Privileged accounts can never log in via OTP — refuse BEFORE any
         * SMS is sent or any OTP row is created.
         *
         * Role-enumeration-safe: the response is byte-for-byte the same
         * shape as a real, successful send (success=true, same message,
         * same cooldown/expires_in, is_registered=true). The send-side
         * throttles (per-IP cap + per-phone cooldown) are consumed first,
         * so even the throttle behaviour of the two flows is identical.
         */
        if ($isRegistered && !$this->isOtpLoginAllowed($existingUser)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Masked phone — never log the full number.
                error_log(
                    sprintf('Arma Panel: OTP login refused for privileged account %s (no code sent).', $this->maskPhoneForLog($canonical))
                );
            }

            // Consume the same send-side throttles a real send would.
            $throttle = $this->otpService->consumeSendThrottle($canonical);

            if (!$throttle['success']) {
                // Identical to the normal throttled-send failure.
                return [
                    'success'  => false,
                    'message'  => $throttle['message'],
                    'cooldown' => $throttle['cooldown'] ?? 0,
                ];
            }

            /*
             * Leave a DECOY OTP row (random unguessable hash, no SMS): a
             * later verify must answer with the SAME "wrong code, N attempts
             * left" message a normal number gets — not with "expired or not
             * found", which would betray the number's privileged status.
             */
            $this->otpService->issueDecoyOtp($canonical);

            // Identical to the normal successful send.
            return [
                'success'       => true,
                'message'       => __('کد تایید با موفقیت ارسال شد.', 'arma-panel'),
                'is_registered' => true,
                'cooldown'      => (int) $this->settings->get('authentication.otp_resend_delay', 60),
                'expires_in'    => (int) $this->settings->get('authentication.otp_expiry', 120),
            ];
        }

        $purpose = $isRegistered ? 'login' : 'register';
        $result = $this->otpService->sendOtp($canonical, $purpose);

        if ($result['success']) {
            $result['is_registered'] = $isRegistered;
        }

        return $result;
    }

    /**
     * Verify an OTP code WITHOUT logging the user in.
     *
     * the controller needs a staged flow (verify → capture guest
     * cart → login → restore cart), so verification and login are separate
     * steps now. verifyAndAuthenticate() remains as a convenience wrapper.
     *
     * @param string $phone
     * @param string $code
     * @return array{success: bool, message: string, user?: WP_User|null, requires_registration?: bool}
     */
    public function verifyOtp(string $phone, string $code): array {
        $canonical = $this->phoneService->normalize($phone);

        if (!$this->phoneService->validate($canonical)) {
            return [
                'success' => false,
                'message' => __('شماره موبایل معتبر نمی‌باشد.', 'arma-panel'),
            ];
        }

        $existingUser = $this->phoneService->findUser($canonical);
        $purpose = ($existingUser instanceof WP_User) ? 'login' : 'register';

        $verifyResult = $this->otpService->verifyOtp($canonical, $code, $purpose);

        if (!$verifyResult['success']) {
            return [
                'success' => false,
                'message' => $verifyResult['message'],
            ];
        }

        // Case 1: Existing (non-privileged) user -> hand back for login.
        if ($existingUser instanceof WP_User) {
            // Defense in depth — a privileged account can never pass
            // verification, even if it somehow got this far. The message is
            // the same generic "wrong or expired code" so the caller cannot
            // learn which numbers are privileged.
            if (!$this->isOtpLoginAllowed($existingUser)) {
                return [
                    'success' => false,
                    'message' => __('کد تایید نادرست یا منقضی شده است.', 'arma-panel'),
                ];
            }

            return [
                'success'               => true,
                'message'               => __('کد تایید با موفقیت تایید شد.', 'arma-panel'),
                'user'                  => $existingUser,
                'requires_registration' => false,
            ];
        }

        // Case 2: New User -> Verified phone, proceed to name collection.
        // the "verified" transient carries its OWN expiry
        // timestamp inside the value and is bound to a random session token
        // delivered as an HttpOnly cookie — a stolen phone number can no
        // longer be registered from another browser by re-using the marker.
        $this->markPhoneVerified($canonical);

        return [
            'success'               => true,
            'message'               => __('شماره موبایل با موفقیت تایید شد. لطفاً نام و نام خانوادگی خود را تکمیل کنید.', 'arma-panel'),
            'user'                  => null,
            'requires_registration' => true,
        ];
    }

    /**
     * Verify OTP and log user in if already registered, or mark verified for registration.
     *
     * @param string $phone
     * @param string $code
     * @return array{success: bool, message: string, requires_registration?: bool, redirect_url?: string}
     */
    public function verifyAndAuthenticate(string $phone, string $code): array {
        $result = $this->verifyOtp($phone, $code);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'],
            ];
        }

        // Existing user -> log them in immediately
        if (($result['user'] ?? null) instanceof WP_User) {
            if (!$this->loginUser($result['user'])) {
                return [
                    'success' => false,
                    'message' => __('در حال حاضر امکان ورود با پیامک برای این شماره وجود ندارد.', 'arma-panel'),
                ];
            }

            return [
                'success'               => true,
                'message'               => __('با موفقیت وارد شدید.', 'arma-panel'),
                'requires_registration' => false,
                'redirect_url'          => $this->getPanelRedirectUrl(),
            ];
        }

        return [
            'success'               => true,
            'message'               => $result['message'],
            'requires_registration' => true,
        ];
    }

    /**
     * Complete registration for a verified new phone number.
     *
     * @param string $phone
     * @param string $firstName
     * @param string $lastName
     * @return array{success: bool, message: string, redirect_url?: string}
     */
    public function completeRegistration(string $phone, string $firstName, string $lastName): array {
        $canonical = $this->phoneService->normalize($phone);

        if (!$this->phoneService->validate($canonical)) {
            return [
                'success' => false,
                'message' => __('شماره موبایل معتبر نمی‌باشد.', 'arma-panel'),
            ];
        }

        // Check if phone was verified by OTP — expiry + session binding
        // (the marker must come from the SAME browser session
        // that verified the OTP and must not have expired).
        if (!$this->isPhoneVerifiedWithSession($canonical)) {
            return [
                'success' => false,
                'message' => __('اعتبارسنجی شماره منقضی شده است. لطفاً فرآیند ورود را مجدداً آغاز نمایید.', 'arma-panel'),
            ];
        }

        // Clean names
        $firstName = sanitize_text_field($firstName);
        $lastName  = sanitize_text_field($lastName);

        if (empty($firstName) || empty($lastName)) {
            return [
                'success' => false,
                'message' => __('لطفاً نام و نام خانوادگی خود را وارد کنید.', 'arma-panel'),
            ];
        }

        // Check uniqueness again to prevent race condition
        if (!$this->phoneService->isUnique($canonical)) {
            return [
                'success' => false,
                'message' => __('این شماره موبایل قبلاً در سایت ثبت‌نام شده است.', 'arma-panel'),
            ];
        }

        // Generate an internal, safe, unique email required by WordPress Core
        // Rule #15: Never displayed to user, never used for login
        $internalEmail = $this->generateInternalEmail($canonical);

        // Generate strong random password
        $randomPassword = wp_generate_password(24, true, true);

        // Create WordPress user
        $userId = wp_insert_user([
            'user_login'   => $canonical,
            'user_email'   => $internalEmail,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'display_name' => trim($firstName . ' ' . $lastName),
            'user_pass'    => $randomPassword,
            'role'         => 'customer',
        ]);

        if (is_wp_error($userId) || !is_int($userId)) {
            $msg = $userId instanceof WP_Error ? $userId->get_error_message() : __('خطا در ایجاد حساب کاربری.', 'arma-panel');
            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        // Assign phone to user meta and WooCommerce billing fields
        $this->phoneService->assignToUser($userId, $canonical);
        update_user_meta($userId, 'billing_first_name', $firstName);
        update_user_meta($userId, 'billing_last_name', $lastName);

        // Clean verification transient + session cookie
        $this->clearPhoneVerified($canonical);

        // Log user in
        $user = get_user_by('id', $userId);
        if ($user instanceof WP_User) {
            $this->loginUser($user);
        }

        return [
            'success'      => true,
            'message'      => __('ثبت‌نام شما با موفقیت تکمیل گردید.', 'arma-panel'),
            'redirect_url' => $this->getPanelRedirectUrl(),
        ];
    }

    /**
     * Generate an internal unique email address to fulfill WordPress Core requirement.
     * Rule #15: Never displayed in panel UI, never used for customer login.
     *
     * @param string $canonicalPhone
     * @return string
     */
    public function generateInternalEmail(string $canonicalPhone): string {
        $host = parse_url(home_url(), PHP_URL_HOST) ?: 'armapanel.local';
        $email = 'u_' . $canonicalPhone . '@' . $host;

        // If email already exists for some reason, append unique hash
        if (email_exists($email)) {
            $email = 'u_' . $canonicalPhone . '_' . substr(md5(uniqid('', true)), 0, 6) . '@' . $host;
        }

        return $email;
    }

    /**
     * Authenticate and set session cookies for user.
     *
     * FINAL guard — if the account turned out to be privileged,
     * no auth cookie is set at all and the method refuses the login.
     *
     * @param WP_User $user
     * @return bool TRUE when the login actually happened.
     */
    public function loginUser(WP_User $user): bool {
        if (!$this->isOtpLoginAllowed($user)) {
            wp_clear_auth_cookie();
            wp_set_current_user(0);

            return false;
        }

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        return true;
    }

    /**
     * Get redirect URL to customer panel.
     *
     * @return string
     */
    public function getPanelRedirectUrl(): string {
        $slug = (string) $this->settings->get('general.panel_slug', 'arma-panel');
        return home_url('/' . trim($slug, '/') . '/');
    }

    /* -----------------------------------------------------------------
     * Phone-verified marker (expiring + session-bound)
     * ----------------------------------------------------------------- */

    private function verifyTransientKey(string $canonical): string {
        return self::VERIFY_TRANSIENT_PREFIX . md5($canonical);
    }

    /**
     * Mark a phone as "verified for registration": the transient VALUE now
     * carries its own expiry timestamp and the hash of a random session
     * token that is set as an HttpOnly cookie on the verifying browser.
     */
    private function markPhoneVerified(string $canonical): void {
        $token = function_exists('wp_generate_password')
            ? wp_generate_password(32, false)
            : bin2hex(random_bytes(16));

        set_transient($this->verifyTransientKey($canonical), [
            'expires' => time() + self::VERIFY_TTL,
            'session' => hash('sha256', $token),
        ], self::VERIFY_TTL);

        $this->issueSessionCookie($token, self::VERIFY_TTL);
    }

    /**
     * Place the OTP session token as an HttpOnly cookie on the verifying
     * browser. (Protected so unit tests can capture the token.)
     */
    protected function issueSessionCookie(string $token, int $ttl): void {
        // HttpOnly: JavaScript can never read the session token.
        if (!headers_sent()) {
            @setcookie(self::SESSION_COOKIE, $token, [
                'expires'  => time() + $ttl,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => function_exists('is_ssl') && is_ssl(),
            ]);
        }
    }

    /**
     * The "verified" marker is only honoured when it has not expired AND the
     * request carries the SAME session cookie the OTP was verified with.
     */
    private function isPhoneVerifiedWithSession(string $canonical): bool {
        $marker = get_transient($this->verifyTransientKey($canonical));

        // Legacy plain "1" values are no longer trusted.
        if (!is_array($marker)) {
            return false;
        }

        $expires = (int) ($marker['expires'] ?? 0);
        if ($expires <= 0 || $expires < time()) {
            return false;
        }

        $cookieToken = $this->readSessionCookie();
        if ($cookieToken === '') {
            return false;
        }

        return hash_equals((string) ($marker['session'] ?? ''), hash('sha256', $cookieToken));
    }

    private function clearPhoneVerified(string $canonical): void {
        delete_transient($this->verifyTransientKey($canonical));

        if (!headers_sent() && function_exists('setcookie')) {
            @setcookie(self::SESSION_COOKIE, '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'secure'   => function_exists('is_ssl') && is_ssl(),
            ]);
        }
    }

    /**
     * Read the OTP session token carried by the current request.
     */
    protected function readSessionCookie(): string {
        return isset($_COOKIE[self::SESSION_COOKIE])
            ? (string) $_COOKIE[self::SESSION_COOKIE]
            : '';
    }

    /**
     * Mask a phone number for debug logs: keep the first 4 and last 2 digits.
     */
    private function maskPhoneForLog(string $phone): string {
        $len = strlen($phone);

        if ($len < 7) {
            return str_repeat('*', $len);
        }

        return substr($phone, 0, 4) . str_repeat('*', $len - 6) . substr($phone, -2);
    }
}
