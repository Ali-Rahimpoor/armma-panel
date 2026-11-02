<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS\Providers;

use Arma\ArmaPanel\Modules\SMS\Contracts\SMSProviderInterface;
use Arma\ArmaPanel\Modules\SMS\SMSResult;

/**
 * SMS.ir provider — pattern/verification sends via the Verify API.
 *
 * Docs: https://api.sms.ir  —  POST https://api.sms.ir/v1/send/verify
 * Auth: HTTP header `X-API-KEY: <panel API key>`.
 *
 * Request JSON:
 *   { "mobile": "09...", "templateId": 123,
 *     "parameters": [ {"name": "Code", "value": "12345"}, ... ] }
 *
 * `templateId` is the NUMERIC template id from the SMS.ir panel (fits the
 * numeric pattern fields). Every template placeholder (#Code#, #Name# …) must
 * receive a parameter whose `name` equals the placeholder name WITHOUT the
 * surrounding hashes — those exact names are provided per message type via the
 * `param_names` option (admin setting), because they are defined by whatever
 * template the site owner created in the SMS.ir panel.
 *
 * Response envelope: {"status": 1, "message": "موفق", "data": …} — success is
 * status === 1.
 */
final class SmsIrProvider implements SMSProviderInterface {

    private const ENDPOINT = 'https://api.sms.ir/v1/send/verify';

    /** @var array<int, string> SMS.ir status → Persian message */
    private const STATUS_MESSAGES = [
        1   => 'موفق.',
        0   => 'خطای سیستمی در SMS.ir؛ کمی بعد تلاش کنید.',
        10  => 'کلید API معتبر نیست.',
        11  => 'کلید API غیرفعال است.',
        12  => 'کلید API به IP خاصی محدود شده است.',
        13  => 'حساب کاربری غیرفعال است.',
        14  => 'حساب کاربری منقضی شده است.',
        20  => 'درخواست بیش از حد مجاز (Rate Limit).',
        101 => 'شماره خط ارسال‌کننده معتبر نیست.',
        102 => 'اعتبار حساب کافی نیست.',
        104 => 'شماره موبایل معتبر نیست.',
        105 => 'تعداد گیرندگان بیش از حد مجاز است.',
        106 => 'متن پیام بیش از حد مجاز است.',
        110 => 'خط ارسال‌کننده با قالب هم‌خوانی ندارد.',
        113 => 'قالب (Template) پیدا نشد.',
        115 => 'گیرنده در لیست سیاه است.',
        117 => 'متن پیام توسط سیستم فیلتر شد.',
        119 => 'قالب با پلن فعلی حساب سازگار نیست.',
        123 => 'خط ارسال‌کننده فعال نیست.',
    ];

    private string $apiKey;
    private bool $sslVerify;

    public function __construct(string $apiKey, bool $sslVerify = true) {
        $this->apiKey    = trim($apiKey);
        $this->sslVerify = $sslVerify; // verify the TLS certificate of the webservice (MITM protection)
    }

    public function getKey(): string {
        return 'smsir';
    }

    public function getTitle(): string {
        return 'SMS.ir';
    }

    /**
     * @param array<string|int> $args
     * @param array{param_names?: string[], line_number?: string, context?: string} $options
     */
    public function sendPattern(string $to, string $pattern, array $args = [], array $options = []): SMSResult {
        if ($this->apiKey === '') {
            return SMSResult::failure(
                'NO_CREDENTIALS',
                __('کلید API سرویس SMS.ir در تنظیمات وارد نشده است.', 'arma-panel')
            );
        }

        $templateId = trim($pattern);
        if ($templateId === '' || !ctype_digit($templateId)) {
            return SMSResult::failure(
                'INVALID_TEMPLATE',
                __('شناسه قالب (Template ID) سرویس SMS.ir باید عددی از پنل SMS.ir باشد.', 'arma-panel')
            );
        }

        // Parameter names must exactly match the template placeholders
        $names = $options['param_names'] ?? [];
        $names = is_array($names) ? array_values($names) : [];
        if ($names === []) {
            return SMSResult::failure(
                'NO_PARAM_NAMES',
                __('نام پارامترهای قالب SMS.ir تنظیم نشده است. در تنظیمات، فیلد «نام پارامترها» را مطابق جایگذاری‌های قالب (بدون #) وارد کنید؛ مثال: Code', 'arma-panel')
            );
        }

        if (count($names) < count($args)) {
            return SMSResult::failure(
                'PARAM_COUNT_MISMATCH',
                sprintf(
                    __('تعداد نام پارامترهای قالب SMS.ir (%d) با تعداد مقادیر ارسالی (%d) برابر نیست. فیلد «نام پارامترها» را به‌روز کنید.', 'arma-panel'),
                    count($names),
                    count($args)
                )
            );
        }

        $parameters = [];
        foreach (array_values($args) as $i => $value) {
            $parameters[] = [
                'name'  => (string) $names[$i],
                'value' => (string) $value,
            ];
        }

        $payload = [
            'mobile'     => $to,
            'templateId' => (int) $templateId,
            'parameters' => $parameters,
        ];

        try {
            $response = $this->httpPost(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            return SMSResult::failure('EXCEPTION', __('خطا در ارتباط با وب‌سرویس SMS.ir.', 'arma-panel'));
        }

        if ($response === null) {
            return SMSResult::failure(
                'HTTP_ERROR',
                __('پاسخی از وب‌سرویس SMS.ir دریافت نشد (احتمالاً WordPress HTTP API در دسترس نیست).', 'arma-panel')
            );
        }

        $body = json_decode($response, true);
        $status = (int) ($body['status'] ?? -1);
        $message = (string) ($body['message'] ?? '');

        if ($status !== 1) {
            $friendly = self::STATUS_MESSAGES[$status] ?? ('خطای SMS.ir؛ کد: ' . $status);
            return SMSResult::failure('SMSIR_' . $status, $friendly . ($message !== '' ? ' (' . $message . ')' : ''));
        }

        $data = $body['data'] ?? [];
        $messageId = '';
        if (is_array($data)) {
            $messageId = (string) ($data['messageId'] ?? '');
            if ($messageId === '' && isset($data['messageIds'][0])) {
                $messageId = (string) $data['messageIds'][0];
            }
        }

        return SMSResult::success(
            $messageId !== '' ? $messageId : (string) time(),
            __('پیامک SMS.ir با موفقیت ثبت شد.', 'arma-panel')
        );
    }

    /**
     * Tiny wrapper so unit tests never touch the network (mockable boundary).
     *
     * @param array<string, mixed> $payload
     * @return string|null Raw response body.
     */
    protected function httpPost(string $url, array $payload): ?string {
        if (!function_exists('wp_remote_post')) {
            return null;
        }

        $response = wp_remote_post($url, [
            'timeout'   => 15,
            'sslverify' => $this->sslVerify,
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->apiKey, // never logged anywhere
            ],
            'body'      => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return is_string($body) && $body !== '' ? $body : null;
    }
}
