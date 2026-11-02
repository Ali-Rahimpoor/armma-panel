<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Authentication;

use Arma\ArmaPanel\Modules\WooCommerce\GuestCheckoutManager;

/**
 * Controller for authentication endpoints (AJAX & form submissions).
 */
class AuthController {

    private AuthService $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    /**
     * Register AJAX action hooks.
     */
    public function registerHooks(): void {
        add_action('wp_ajax_nopriv_arma_send_otp', [$this, 'handleSendOtp']);
        add_action('wp_ajax_arma_send_otp', [$this, 'handleSendOtp']);

        add_action('wp_ajax_nopriv_arma_verify_otp', [$this, 'handleVerifyOtp']);
        add_action('wp_ajax_arma_verify_otp', [$this, 'handleVerifyOtp']);

        add_action('wp_ajax_nopriv_arma_complete_registration', [$this, 'handleCompleteRegistration']);
        add_action('wp_ajax_arma_complete_registration', [$this, 'handleCompleteRegistration']);
    }

    /**
     * Handle OTP send request.
     */
    public function handleSendOtp(): void {
        check_ajax_referer('arma_auth_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (empty($phone)) {
            wp_send_json_error([
                'message' => __('لطفاً شماره موبایل را وارد نمایید.', 'arma-panel'),
            ]);
        }

        $result = $this->authService->requestOtp($phone);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle OTP verification request.
     *
     * strictly staged flow:
     *   1. verify the code (no login yet);
     *   2. ONLY on success: capture the guest cart (random token cookie) —
     *      on BOTH paths (existing user logging in, and the new-number
     *      registration flow that follows);
     *   3. log the user in;
     *   4. restore the captured cart into the fresh session.
     * A failed verification never touches the cart at all.
     */
    public function handleVerifyOtp(): void {
        check_ajax_referer('arma_auth_nonce', 'nonce');

        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $code  = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';

        if (empty($phone) || empty($code)) {
            wp_send_json_error([
                'message' => __('لطفاً شماره موبایل و کد تایید را وارد کنید.', 'arma-panel'),
            ]);
        }

        // 1. Verification only — nothing else happens on a bad code.
        $result = $this->authService->verifyOtp($phone, $code);

        if (!$result['success']) {
            wp_send_json_error($result);
        }

        // 2. New number: phone is verified, registration (name) comes next.
        //    The guest cart is captured NOW (same as the login path), so the
        //    cart survives the register step that ends in a fresh session.
        if (!empty($result['requires_registration'])) {
            GuestCheckoutManager::captureGuestCart();

            wp_send_json_success([
                'success'               => true,
                'message'               => $result['message'],
                'requires_registration' => true,
            ]);
        }

        // 3. Existing user: capture the guest cart, THEN log in.
        GuestCheckoutManager::captureGuestCart();

        $user = $result['user'] ?? null;
        if ($user !== null && !$this->authService->loginUser($user)) {
            wp_send_json_error([
                'message' => __('در حال حاضر امکان ورود با پیامک برای این شماره وجود ندارد.', 'arma-panel'),
            ]);
        }

        // 4. Bring the guest cart into the freshly authenticated session.
        GuestCheckoutManager::restoreGuestCart();

        $payload = [
            'success'               => true,
            'message'               => __('با موفقیت وارد شدید.', 'arma-panel'),
            'requires_registration' => false,
        ];

        // Return the user to where they started (cart/checkout gating).
        $target = $this->resolveRedirectTarget();
        $payload['redirect_url'] = $target !== '' ? $target : $this->authService->getPanelRedirectUrl();

        wp_send_json_success($payload);
    }

    /**
     * Handle completing new user registration with First & Last name.
     */
    public function handleCompleteRegistration(): void {
        check_ajax_referer('arma_auth_nonce', 'nonce');

        $phone     = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $firstName = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $lastName  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';

        if (empty($phone) || empty($firstName) || empty($lastName)) {
            wp_send_json_error([
                'message' => __('لطفاً تمام فیلدهای نام و نام خانوادگی را وارد کنید.', 'arma-panel'),
            ]);
        }

        $result = $this->authService->completeRegistration($phone, $firstName, $lastName);

        if ($result['success']) {
            // Restore any cart captured at the verify step (token cookie) —
            // captured on BOTH the login and registration paths.
            GuestCheckoutManager::restoreGuestCart();

            $target = $this->resolveRedirectTarget();
            if ($target !== '') {
                $result['redirect_url'] = $target;
            }

            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Resolve the validated ?redirect_to target sent by the login form
     * (same-site only — prevents open redirects).
     */
    private function resolveRedirectTarget(): string {
        $raw = isset($_POST['redirect_to']) ? (string) wp_unslash($_POST['redirect_to']) : '';

        if ($raw === '') {
            return '';
        }

        return arma_safe_redirect_target($raw, '');
    }
}
