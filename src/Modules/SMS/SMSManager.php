<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS;

use Arma\ArmaPanel\Modules\SMS\Providers\KavenegarProvider;
use Arma\ArmaPanel\Modules\SMS\Providers\SmsIrProvider;
use Arma\ArmaPanel\Modules\SMS\Providers\FarazSmsProvider;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\SMS\Contracts\SMSProviderInterface;
use Arma\ArmaPanel\Modules\SMS\Providers\MelipayamakProvider;
use Throwable;

/**
 * SMS Manager coordinating providers, pattern dispatching, and safe logging.
 */
class SMSManager {

    private SettingsManager $settings;

    /**
     * Registered SMS providers.
     *
     * @var array<string, SMSProviderInterface>
     */
    private array $providers = [];

    public function __construct(SettingsManager $settings) {
        $this->settings = $settings;
        $this->registerDefaultProviders();
    }

    /**
     * Register default Melipayamak provider.
     */
    private function registerDefaultProviders(): void {
        $username = (string) $this->settings->get('sms.username', '');
        $password = (string) $this->settings->get('sms.password', '');

        // TLS certificate verification is UNCONDITIONAL (v1.3.0) — connecting
        // to the SMS webservice without verifying its certificate would allow
        // man-in-the-middle attacks on credentials & OTP codes.
        $sslVerify = true;
        $this->registerProvider(new MelipayamakProvider($username, $password, $sslVerify));
        $this->registerProvider(new KavenegarProvider(
            (string) $this->settings->get('sms.kavenegar_api_key', ''),
            $sslVerify
        ));
        $this->registerProvider(new SmsIrProvider(
            (string) $this->settings->get('sms.smsir_api_key', ''),
            $sslVerify
        ));
        $this->registerProvider(new FarazSmsProvider(
            (string) $this->settings->get('sms.faraz_api_key', ''),
            (string) $this->settings->get('sms.faraz_line_number', ''),
            $sslVerify
        ));
    }

    /**
     * Register a new SMS provider.
     *
     * @param SMSProviderInterface $provider
     */
    public function registerProvider(SMSProviderInterface $provider): void {
        $this->providers[$provider->getKey()] = $provider;
    }

    /**
     * Get active SMS provider.
     *
     * @return SMSProviderInterface|null
     */
    public function getActiveProvider(): ?SMSProviderInterface {
        $providerKey = (string) $this->settings->get('sms.provider', 'melipayamak');
        return $this->providers[$providerKey] ?? ($this->providers['melipayamak'] ?? null);
    }

    /**
     * Halt every send when the stored credentials can no longer be decrypted
     * (site salts changed / DB moved): the values would only produce failed
     * authentication attempts against the SMS panel. Returns a failure
     * result to short-circuit the caller, or null when everything is fine.
     */
    private function lockedCredentialsFailure(): ?SMSResult {
        if (!$this->settings->hasUndecryptableCredential()) {
            return null;
        }

        $this->logError(
            'CREDENTIALS_LOCKED',
            'Stored SMS panel credentials cannot be decrypted with the current site key; SMS sending is halted. Re-enter the credentials in Arma Panel → Settings → SMS.'
        );

        return SMSResult::failure(
            'CREDENTIALS_LOCKED',
            __('اطلاعات پنل پیامک با کلید فعلی سایت قابل خواندن نیست؛ لطفاً دوباره وارد کنید.', 'arma-panel')
        );
    }

    /**
     * Send OTP verification SMS using configured Pattern.
     *
     * @param string $phone
     * @param string $code
     * @return SMSResult
     */
    public function sendOtp(string $phone, string $code): SMSResult {
        if (($halt = $this->lockedCredentialsFailure()) !== null) {
            return $halt;
        }

        $provider = $this->getActiveProvider();
        if (!$provider) {
            return SMSResult::failure('NO_PROVIDER', __('هیچ سرویس‌دهنده پیامکی فعالی یافت نشد.', 'arma-panel'));
        }

        // Check authentication.otp_pattern first, then fallback to sms.otp_pattern
        $patternId = $this->patternSetting('authentication.otp_pattern', 'sms.otp_pattern');

        if ($patternId === '') {
            return SMSResult::failure('NO_PATTERN', __('کد پترن (Body ID) پیامک اعتبارسنجی در بخش تنظیمات ورود و ثبت‌نام مشخص نشده است.', 'arma-panel'));
        }

        try {
            // Melipayamak pattern parameter for OTP is typically single variable {0}
            $result = $provider->sendPattern($phone, $patternId, [$code], $this->patternOptions('otp'));

            if (!$result->isSuccessful()) {
                $this->logError('OTP_SMS_FAILED', $result->getMessage(), [
                    'phone_masked' => $this->maskPhone($phone),
                    'error_code'   => $result->getErrorCode(),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logError('OTP_SMS_EXCEPTION', $e->getMessage(), [
                'phone_masked' => $this->maskPhone($phone),
            ]);

            return SMSResult::failure('EXCEPTION', __('خطای پیش‌بینی نشده در ارسال پیامک.', 'arma-panel'));
        }
    }

    /**
     * Send Order Confirmation SMS.
     *
     * @param string $phone
     * @param string $orderNumber
     * @param array<string|int> $extraArgs
     * @return SMSResult
     */
    public function sendOrderConfirmation(string $phone, string $orderNumber, array $extraArgs = []): SMSResult {
        // Order SMS module must be enabled
        if (!$this->settings->isModuleEnabled('order_sms')) {
            return SMSResult::success('SKIPPED', 'Order SMS module is disabled.');
        }

        $isOrderSmsEnabled = (bool) $this->settings->get('wc_sms.enabled', true);
        if (!$isOrderSmsEnabled) {
            return SMSResult::success('SKIPPED', 'Order SMS is disabled in settings.');
        }

        if (($halt = $this->lockedCredentialsFailure()) !== null) {
            return $halt;
        }

        $provider = $this->getActiveProvider();
        if (!$provider) {
            return SMSResult::failure('NO_PROVIDER', __('سرویس‌دهنده پیامک فعال نیست.', 'arma-panel'));
        }

        $patternId = $this->patternSetting('wc_sms.order_pattern', 'sms.order_pattern');

        if ($patternId === '') {
            return SMSResult::failure('NO_PATTERN', __('کد پترن پیامک سفارش در بخش پیامک‌های ووکامرس ثبت نشده است.', 'arma-panel'));
        }

        $args = array_merge([$orderNumber], $extraArgs);

        try {
            $result = $provider->sendPattern($phone, $patternId, $args, $this->patternOptions('order'));

            if (!$result->isSuccessful()) {
                $this->logError('ORDER_SMS_FAILED', $result->getMessage(), [
                    'order'        => $orderNumber,
                    'phone_masked' => $this->maskPhone($phone),
                    'error_code'   => $result->getErrorCode(),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logError('ORDER_SMS_EXCEPTION', $e->getMessage(), [
                'order'        => $orderNumber,
                'phone_masked' => $this->maskPhone($phone),
            ]);

            return SMSResult::failure('EXCEPTION', __('خطا در ارسال پیامک سفارش.', 'arma-panel'));
        }
    }

    /**
     * Send shipment tracking SMS.
     * Pattern: «مشتری عزیز سفارش شما توسط {0} ارسال شد ، کد رهگیری {1}»
     *
     * @param string $phone
     * @param string $methodName
     * @param string $trackingCode
     * @return SMSResult
     */
    public function sendShippingTracking(string $phone, string $methodName, string $trackingCode): SMSResult {
        if (($halt = $this->lockedCredentialsFailure()) !== null) {
            return $halt;
        }

        $provider = $this->getActiveProvider();
        if (!$provider) {
            return SMSResult::failure('NO_PROVIDER', __('سرویس‌دهنده پیامک فعال نیست.', 'arma-panel'));
        }

        // Pattern resolution with graceful fallback (v1.3.2):
        //  - method + shipping pattern  → 2-variable pattern «توسط {روش} ارسال شد ، کد {کد}»
        //  - method but NO shipping pattern → fall back to the tracking-only
        //    pattern with just the code (the method is simply not part of the
        //    SMS text) — previously this silently failed with NO_PATTERN.
        //  - no method → tracking-only pattern.
        $shippingPattern = $this->patternSetting('wc_sms.shipping_pattern');
        $trackingPattern = $this->patternSetting('wc_sms.tracking_code_pattern');

        if ($methodName !== '' && $shippingPattern !== '') {
            $patternId = $shippingPattern;
            $args      = [$methodName, $trackingCode];
            $context   = 'shipping';
        } elseif ($trackingPattern !== '' && ($methodName === '' || $shippingPattern === '')) {
            $patternId = $trackingPattern;
            $args      = [$trackingCode];
            $context   = 'tracking';
        } else {
            return SMSResult::failure('NO_PATTERN', $shippingPattern === ''
                ? __('هیچ کد پتری برای پیامک ارسال/رهگیری مرسوله ثبت نشده است. در بخش «پیامک ووکامرس ← ۲) پیامک ارسال و رهگیری مرسوله» حداقل یکی از پترن‌های «ارسال مرسوله» یا «فقط کد رهگیری» را پر کنید.', 'arma-panel')
                : __('کد پترن «فقط کد رهگیری» (روش حمل خالی) در بخش پیامک‌های ووکامرس ثبت نشده است.', 'arma-panel'));
        }

        try {
            $result = $provider->sendPattern($phone, $patternId, $args, $this->patternOptions($context));

            if (!$result->isSuccessful()) {
                $this->logError('SHIPPING_SMS_FAILED', $result->getMessage(), [
                    'phone_masked' => $this->maskPhone($phone),
                    'error_code'   => $result->getErrorCode(),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logError('SHIPPING_SMS_EXCEPTION', $e->getMessage(), [
                'phone_masked' => $this->maskPhone($phone),
            ]);

            return SMSResult::failure('EXCEPTION', __('خطا در ارسال پیامک رهگیری مرسوله.', 'arma-panel'));
        }
    }

    /**
     * Send abandoned-cart reminder SMS.
     * Suggested pattern: «... سبد خرید شما ... کد تخفیف {0} ...» ({1} = percent)
     *
     * @param string $phone
     * @param string $couponCode
     * @param int    $percent
     * @return SMSResult
     */
    public function sendAbandonedCartReminder(string $phone, string $couponCode, int $percent): SMSResult {
        if (($halt = $this->lockedCredentialsFailure()) !== null) {
            return $halt;
        }

        $provider = $this->getActiveProvider();
        if (!$provider) {
            return SMSResult::failure('NO_PROVIDER', __('سرویس‌دهنده پیامک فعال نیست.', 'arma-panel'));
        }

        $patternId = $this->patternSetting('wc_sms.abandoned_pattern');
        if ($patternId === '') {
            return SMSResult::failure('NO_PATTERN', __('کد پترن پیامک یادآوری سبد خرید ثبت نشده است.', 'arma-panel'));
        }

        try {
            $result = $provider->sendPattern($phone, $patternId, [$couponCode, (string) $percent], $this->patternOptions('abandoned'));

            if (!$result->isSuccessful()) {
                $this->logError('ABANDONED_SMS_FAILED', $result->getMessage(), [
                    'phone_masked' => $this->maskPhone($phone),
                    'error_code'   => $result->getErrorCode(),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logError('ABANDONED_SMS_EXCEPTION', $e->getMessage(), [
                'phone_masked' => $this->maskPhone($phone),
            ]);

            return SMSResult::failure('EXCEPTION', __('خطا در ارسال پیامک یادآوری سبد خرید.', 'arma-panel'));
        }
    }

    /**
     * Send a test SMS to verify provider connectivity.
     *
     * @param string $phone
     * @param int|null $testPatternId
     * @return SMSResult
     */
    public function sendTestSms(string $phone, ?int $testPatternId = null): SMSResult {
        return $this->sendTestSmsWithPattern($phone, $testPatternId !== null ? (string) $testPatternId : null);
    }

    /**
     * Test send accepting a provider-agnostic pattern identifier (name or id).
     */
    public function sendTestSmsWithPattern(string $phone, ?string $testPattern = null): SMSResult {
        if (($halt = $this->lockedCredentialsFailure()) !== null) {
            return $halt;
        }

        $provider = $this->getActiveProvider();
        if (!$provider) {
            return SMSResult::failure('NO_PROVIDER', __('سرویس‌دهنده پیامک فعال نیست.', 'arma-panel'));
        }

        $candidates = [$testPattern ?? ''];
        if ($candidates[0] === '') {
            $candidates = [
                $this->patternSetting('authentication.otp_pattern'),
                $this->patternSetting('wc_sms.order_pattern'),
                $this->patternSetting('sms.otp_pattern'),
            ];
        }
        $patternId = '';
        foreach ($candidates as $c) {
            if ($c !== '') { $patternId = $c; break; }
        }

        if ($patternId === '') {
            return SMSResult::failure('NO_PATTERN', __('کد پترنی برای ارسال پیامک تست یافت نشد. لطفاً کد پترن را در فیلد مربوطه وارد نمایید.', 'arma-panel'));
        }

        $testCode = (string) random_int(10000, 99999);
        return $provider->sendPattern($phone, $patternId, [$testCode], $this->patternOptions('test'));
    }

    /**
     * Build provider-specific send options for a message context. SMS.ir and
     * FarazSMS templates carry NAMED variables — the admin configures the
     * names (comma separated, in order) per message type; positional providers
     * (Melipayamak / Kavenegar) get an empty option set.
     *
     * @param string $context otp|order|shipping|tracking|abandoned|test
     * @return array{param_names?: string[], line_number?: string, context: string}
     */
    private function patternOptions(string $context): array {
        $options = ['context' => $context];
        $provider = $this->getActiveProvider();
        $providerKey = $provider ? $provider->getKey() : '';

        if ($providerKey === 'smsir' || $providerKey === 'faraz') {
            $scopeMap = [
                'otp'        => 'authentication',
                'order'      => 'wc_sms',
                'shipping'   => 'wc_sms',
                'tracking'   => 'wc_sms',
                'abandoned'  => 'wc_sms',
                'test'       => 'authentication',
            ];

            $scope = $scopeMap[$context] ?? 'wc_sms';

            // "test" may resolve to the order pattern when OTP is unset
            $keys = [$scope . '.' . $providerKey . '_' . $context . '_params'];
            if ($context === 'test') {
                $keys[] = 'wc_sms.' . $providerKey . '_order_params';
                $keys[] = 'authentication.' . $providerKey . '_otp_params';
            }

            foreach ($keys as $key) {
                $raw = trim((string) $this->settings->get($key, ''));
                if ($raw !== '') {
                    $names = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn ($n) => $n !== ''));
                    if ($names !== []) {
                        $options['param_names'] = $names;
                        break;
                    }
                }
            }
        }

        if ($providerKey === 'faraz') {
            $options['line_number'] = trim((string) $this->settings->get('sms.faraz_line_number', ''));
        }

        return $options;
    }

    /**
     * Resolve a pattern setting to a non-empty string. Pattern identifiers are
     * provider-specific (numeric BodyId, template name, pattern code) — a plain
     * '0' means "not set" for every provider and maps to ''.
     */
    private function patternSetting(string ...$keys): string {
        foreach ($keys as $key) {
            $value = trim((string) $this->settings->get($key, ''));
            if ($value !== '' && $value !== '0') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Mask phone number for safe logging (e.g. 0912***6789).
     */
    private function maskPhone(string $phone): string {
        if (strlen($phone) < 7) {
            return '***';
        }
        return substr($phone, 0, 4) . '***' . substr($phone, -4);
    }

    /**
     * Log SMS errors safely without exposing passwords, OTPs, or API keys.
     *
     * @param string $type
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logError(string $type, string $message, array $context = []): void {
        /*
         * Debug only — SMS diagnostics never reach production logs. Applies
         * to BOTH sinks (WooCommerce logger and error_log): a busy store
         * would otherwise fill its log with phone/provider chatter.
         */
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->error(sprintf('[ArmaPanel SMS %s] %s %s', $type, $message, wp_json_encode($context)), [
                'source' => 'arma-panel-sms',
            ]);
        } else {
            error_log(sprintf('[ArmaPanel SMS %s] %s %s', $type, $message, wp_json_encode($context)));
        }
    }
}
