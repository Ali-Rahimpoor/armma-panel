<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS\Providers;

use Arma\ArmaPanel\Modules\SMS\Contracts\SMSProviderInterface;
use Arma\ArmaPanel\Modules\SMS\SMSResult;

/**
 * FarazSMS (api.iranpayamak.com) provider — pattern sends.
 *
 * Docs: POST https://api.iranpayamak.com/ws/v1/sms/pattern
 * Auth: HTTP header `Api-Key: <static panel API key>` — header name is
 * case-sensitive per the official docs (apikey/access-key → 401). The static
 * panel API key must be used, NEVER a Login token.
 *
 * Request JSON:
 *   { "code": "SJ3FgPrE0C",            ← pattern UID from the FarazSMS panel
 *     "attributes": { "var": "value" }, ← keys must EXACTLY match the pattern
 *                                         variable names (%var%); every declared
 *                                         variable is required
 *     "recipient": "09123456789",       ← STRICT ^09\d{9}$ (no +98 / 0098)
 *     "line_number": "900...",          ← sender line from the panel
 *     "number_format": "english" }      ← optional
 *
 * Variable names differ per pattern, so they are provided per message type via
 * the `param_names` option (admin setting) — never guessed.
 *
 * Response: HTTP 201 + {"status":"success","data":"<send_request id>"} means
 * the request was ACCEPTED (charge applies); per-recipient delivery state is
 * later available at GET /ws/v1/send_request/{id}/items. NOTE: values longer
 * than the declared variable length are silently rerouted to a human-moderator
 * queue while still returning success — so arg lengths are the admin's
 * responsibility.
 */
final class FarazSmsProvider implements SMSProviderInterface {

    private const ENDPOINT = 'https://api.iranpayamak.com/ws/v1/sms/pattern';

    private string $apiKey;
    private string $lineNumber;
    private bool $sslVerify;

    public function __construct(string $apiKey, string $lineNumber = '', bool $sslVerify = true) {
        $this->apiKey    = trim($apiKey);
        $this->lineNumber = trim($lineNumber);
        $this->sslVerify = $sslVerify; // verify the TLS certificate of the webservice (MITM protection)
    }

    public function getKey(): string {
        return 'faraz';
    }

    public function getTitle(): string {
        return 'فراز اس‌ام‌اس (ایران‌پیامک)';
    }

    /**
     * @param array<string|int> $args
     * @param array{param_names?: string[], line_number?: string, context?: string} $options
     */
    public function sendPattern(string $to, string $pattern, array $args = [], array $options = []): SMSResult {
        if ($this->apiKey === '') {
            return SMSResult::failure(
                'NO_CREDENTIALS',
                __('کلید Api-Key فراز اس‌ام‌اس در تنظیمات وارد نشده است.', 'arma-panel')
            );
        }

        $code = trim($pattern);
        if ($code === '') {
            return SMSResult::failure(
                'NO_PATTERN',
                __('کد پترن فراز اس‌ام‌اس (مثل SJ3FgPrE0C) تنظیم نشده است.', 'arma-panel')
            );
        }

        $lineNumber = trim((string) ($options['line_number'] ?? '')) ?: $this->lineNumber;
        if ($lineNumber === '') {
            return SMSResult::failure(
                'NO_LINE_NUMBER',
                __('شماره خط فرستنده فراز اس‌ام‌اس در تنظیمات وارد نشده است.', 'arma-panel')
            );
        }

        // FarazSMS requires the strict ^09\d{9}$ format — normalize common
        // Iranian formats first (+98/0098/98/leading-9).
        $recipient = $this->normalizeRecipient($to);
        if ($recipient === null) {
            return SMSResult::failure(
                'INVALID_RECIPIENT',
                __('شماره گیرنده برای فراز اس‌ام‌اس باید با ۰۹ شروع شده و ۱۱ رقم باشد (فرمت +98/0098 پذیرفته نیست و به‌صورت خودکار تبدیل می‌شود).', 'arma-panel')
            );
        }

        // Attribute names must exactly match the pattern variables (%var%)
        $names = $options['param_names'] ?? [];
        $names = is_array($names) ? array_values($names) : [];
        if ($names === []) {
            return SMSResult::failure(
                'NO_PARAM_NAMES',
                __('نام متغیرهای پترن فراز اس‌ام‌اس تنظیم نشده است. در تنظیمات، فیلد «نام متغیرها» را دقیقاً مطابق متغیرهای تعریف‌شده پترن (بدون %) و به ترتیب وارد کنید؛ مثال: code', 'arma-panel')
            );
        }

        if (count($names) < count($args)) {
            return SMSResult::failure(
                'PARAM_COUNT_MISMATCH',
                sprintf(
                    __('تعداد نام متغیرهای پترن فراز اس‌ام‌اس (%d) با تعداد مقادیر ارسالی (%d) برابر نیست. فیلد «نام متغیرها» را به‌روز کنید.', 'arma-panel'),
                    count($names),
                    count($args)
                )
            );
        }

        $attributes = [];
        foreach (array_values($args) as $i => $value) {
            $attributes[(string) $names[$i]] = (string) $value;
        }

        $payload = [
            'code'       => $code,
            'attributes' => $attributes,
            'recipient'  => $recipient,
            'line_number'=> $lineNumber,
        ];

        try {
            $response = $this->httpPost(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            return SMSResult::failure('EXCEPTION', __('خطا در ارتباط با وب‌سرویس فراز اس‌ام‌اس.', 'arma-panel'));
        }

        if ($response === null) {
            return SMSResult::failure(
                'HTTP_ERROR',
                __('پاسخی از وب‌سرویس فراز اس‌ام‌اس دریافت نشد (احتمالاً WordPress HTTP API در دسترس نیست).', 'arma-panel')
            );
        }

        $body = json_decode($response, true);
        $status = (string) ($body['status'] ?? '');
        $sendRequestId = isset($body['data']) && is_scalar($body['data']) ? (string) $body['data'] : '';

        if ($status !== 'success') {
            $detail = '';
            if (is_array($body) && isset($body['message']) && is_scalar($body['message'])) {
                $detail = ' (' . (string) $body['message'] . ')';
            } elseif (is_array($body) && isset($body['error']) && is_scalar($body['error'])) {
                $detail = ' (' . (string) $body['error'] . ')';
            }
            return SMSResult::failure('FARAZ_ERROR', __('ارسال پترن فراز اس‌ام‌اس ناموفق بود.', 'arma-panel') . $detail);
        }

        return SMSResult::success(
            $sendRequestId !== '' ? $sendRequestId : (string) time(),
            __('درخواست پترن فراز اس‌ام‌اس پذیرفته شد (شناسه پیگیری ثبت شد).', 'arma-panel')
        );
    }

    /**
     * Normalize Iranian mobile formats to the strict ^09\d{9}$ FarazSMS shape.
     * Returns null when the number can never be valid.
     */
    private function normalizeRecipient(string $phone): ?string {
        $digits = preg_replace('/[\s\-()]/', '', $phone) ?? '';

        if (preg_match('/^\+98(9\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }
        if (preg_match('/^0098(9\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }
        if (preg_match('/^98(9\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }
        if (preg_match('/^9\d{9}$/', $digits)) {
            return '0' . $digits;
        }
        if (preg_match('/^09\d{9}$/', $digits)) {
            return $digits;
        }

        return null;
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
                // Header name is case-sensitive per FarazSMS docs
                'Api-Key'      => $this->apiKey, // never logged anywhere
            ],
            'body'      => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 0 && $code !== 201 && $code !== 200) {
            // Non-2xx (429 rate-limit etc.) — surface body when parsable
            return is_string($body) && $body !== '' ? $body : null;
        }

        return is_string($body) && $body !== '' ? $body : null;
    }
}
