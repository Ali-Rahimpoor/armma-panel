<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS\Providers;

use Arma\ArmaPanel\Modules\SMS\Contracts\SMSProviderInterface;
use Arma\ArmaPanel\Modules\SMS\SMSResult;

/**
 * Kavenegar provider — pattern/verification sends via the Verify Lookup API.
 *
 * Docs: https://api.kavenegar.com/v1/{API-KEY}/verify/lookup.json
 * Auth: the account API-KEY is part of the URL path (per Kavenegar REST design).
 *
 * Mapping from our ordered args to Kavenegar's fixed token names:
 *   args[0] → token, args[1] → token2, args[2] → token3
 *   (lookup supports up to token/token2/token3/token10/token20 — more than we ever send)
 * The "pattern" value for this provider is the TEMPLATE NAME defined in the
 * Kavenegar panel (English, no spaces — e.g. "LoginOtp").
 */
final class KavenegarProvider implements SMSProviderInterface {

    private const BASE = 'https://api.kavenegar.com/v1/';

    /** @var array<int, string> Kavenegar return-status → Persian message */
    private const STATUS_MESSAGES = [
        200 => 'تایید شد.',
        400 => 'پارامترهای ارسالی به کاوه‌نگار ناقص است.',
        401 => 'حساب کاربری کاوه‌نگار غیرفعال است.',
        402 => 'عملیات ناموفق بود.',
        403 => 'کلید API کاوه‌نگار معتبر نمی‌باشد.',
        404 => 'متد درخواستی تعریف نشده است.',
        405 => 'متد فراخوانی Get/Post اشتباه است.',
        406 => 'پارامترهای اجباری (گیرنده/قالب/توکن) خالی ارسال شده‌اند.',
        409 => 'سرور کاوه‌نگار پاسخگو نیست؛ کمی بعد تلاش کنید.',
        411 => 'شماره گیرنده معتبر نمی‌باشد.',
        412 => 'شماره فرستنده معتبر نمی‌باشد.',
        413 => 'متن پیام خالی است یا بیش از حد مجاز است.',
        414 => 'حجم درخواست بیشتر از حد مجاز است.',
        417 => 'تاریخ ارسال اشتباه است.',
        418 => 'اعتبار حساب کاوه‌نگار کافی نیست.',
        422 => 'داده‌ها به دلیل کاراکتر نامناسب قابل پردازش نیستند.',
        424 => 'الگوی (Template) کاوه‌نگار پیدا نشد؛ نام قالب یا تایید آن را بررسی کنید.',
        426 => 'استفاده از این سرویس نیازمند فعال‌سازی سرویس پیشرفته کاوه‌نگار است.',
        429 => 'IP محدود شده است.',
        431 => 'ساختار کد (توکن) صحیح نمی‌باشد.',
        432 => 'پارامتر توکن (%token) در متن الگو تعریف نشده است.',
        451 => 'فراخوانی بیش از حد مجاز؛ لطفاً کمی بعد تلاش کنید.',
        501 => 'فقط امکان ارسال پیام تست به شماره صاحب حساب وجود دارد.',
    ];

    private string $apiKey;
    private bool $sslVerify;

    public function __construct(string $apiKey, bool $sslVerify = true) {
        $this->apiKey   = trim($apiKey);
        $this->sslVerify = $sslVerify; // verify the TLS certificate of the webservice (MITM protection)
    }

    public function getKey(): string {
        return 'kavenegar';
    }

    public function getTitle(): string {
        return 'کاوه‌نگار (Kavenegar)';
    }

    public function sendPattern(string $to, string $pattern, array $args = [], array $options = []): SMSResult {
        if ($this->apiKey === '') {
            return SMSResult::failure(
                'NO_CREDENTIALS',
                __('کلید API کاوه‌نگار در تنظیمات وارد نشده است.', 'arma-panel')
            );
        }

        $template = trim($pattern);
        if ($template === '') {
            return SMSResult::failure(
                'NO_TEMPLATE',
                __('نام قالب (Template) کاوه‌نگار تنظیم نشده است.', 'arma-panel')
            );
        }

        // Ordered args → Kavenegar fixed token names
        $params = [
            'receptor' => $to,
            'template' => $template,
        ];
        $tokenKeys = ['token', 'token2', 'token3'];
        foreach ($tokenKeys as $i => $key) {
            if (isset($args[$i]) && $args[$i] !== '') {
                $params[$key] = (string) $args[$i];
            }
        }

        $url = self::BASE . rawurlencode($this->apiKey) . '/verify/lookup.json?' . http_build_query($params);

        try {
            $response = $this->httpGet($url);
        } catch (\Throwable $e) {
            return SMSResult::failure('EXCEPTION', __('خطا در ارتباط با وب‌سرویس کاوه‌نگار.', 'arma-panel'));
        }

        if ($response === null) {
            return SMSResult::failure(
                'HTTP_ERROR',
                __('پاسخی از وب‌سرویس کاوه‌نگار دریافت نشد (احتمالاً WordPress HTTP API در دسترس نیست).', 'arma-panel')
            );
        }

        $body = json_decode($response, true);
        $status = (int) ($body['return']['status'] ?? 0);
        $message = (string) ($body['return']['message'] ?? '');

        if ($status !== 200) {
            $friendly = self::STATUS_MESSAGES[$status] ?? ('خطای کاوه‌نگار؛ کد: ' . $status);
            return SMSResult::failure('KV_' . $status, $friendly . ($message !== '' ? ' (' . $message . ')' : ''));
        }

        $entries = $body['entries'] ?? null;
        $entry = is_array($entries) ? ($entries[0] ?? []) : (is_array($entries) ? $entries : []);
        $messageId = (string) ($entry['messageid'] ?? '');

        return SMSResult::success(
            $messageId !== '' ? $messageId : (string) time(),
            __('پیامک کاوه‌نگار با موفقیت ثبت شد.', 'arma-panel')
        );
    }

    /**
     * Tiny wrapper so unit tests never touch the network (mockable boundary).
     *
     * @return string|null Raw response body.
     */
    protected function httpGet(string $url): ?string {
        if (!function_exists('wp_remote_get')) {
            return null;
        }

        $response = wp_remote_get($url, [
            'timeout'   => 15,
            'sslverify' => $this->sslVerify,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return is_string($body) && $body !== '' ? $body : null;
    }
}
