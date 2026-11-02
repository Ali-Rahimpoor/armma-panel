<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS\Providers;

use Arma\ArmaPanel\Modules\SMS\Contracts\SMSProviderInterface;
use Arma\ArmaPanel\Modules\SMS\SMSResult;
use SoapClient;
use Throwable;

/**
 * Melipayamak SMS Provider implementation.
 * Built strictly according to official Melipayamak documentation for SendByBaseNumber2.
 */
class MelipayamakProvider implements SMSProviderInterface {

    public const REST_ENDPOINT = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
    public const SOAP_WSDL     = 'https://api.payamak-panel.com/post/send.asmx?wsdl';

    private string $username;
    private string $password;
    private bool $sslVerify;

    public function __construct(string $username, string $password, bool $sslVerify = true) {
        $this->username = trim($username);
        $this->password = trim($password);
        $this->sslVerify = $sslVerify; // verify the TLS certificate of the SMS webservice (MITM protection)
    }

    public function getKey(): string {
        return 'melipayamak';
    }

    public function getTitle(): string {
        return 'ملی پیامک (Melipayamak)';
    }

    /**
     * Send pattern SMS using SendByBaseNumber2.
     *
     * @param string $to Single mobile phone number.
     * @param string $pattern Approved pattern ID (numeric string).
     * @param array<string|int> $args Ordered parameters.
     * @param array<string, mixed> $options Ignored (positional {0}{1} mapping).
     * @return SMSResult
     */
    public function sendPattern(string $to, string $pattern, array $args = [], array $options = []): SMSResult {
        if (empty($this->username) || empty($this->password)) {
            return SMSResult::failure(
                'NO_CREDENTIALS',
                __('نام کاربری یا رمز عبور پنل پیامک تنظیم نشده است.', 'arma-panel')
            );
        }

        $bodyId = (int) trim($pattern);
        if ($bodyId <= 0) {
            return SMSResult::failure(
                'INVALID_BODY_ID',
                __('کد پترن (Body ID) پیامک نامعتبر است.', 'arma-panel')
            );
        }

        // Clean parameters (no semicolons inside arguments, joined by semicolon)
        $cleanArgs = array_map(function ($arg) {
            return str_replace(';', ' ', (string) $arg);
        }, $args);

        $text = implode(';', $cleanArgs);

        // Try REST first via wp_remote_post or cURL
        $result = $this->sendViaRest($to, $bodyId, $text);

        // If REST had network connection issue and Soap is available, try SOAP fallback
        if (!$result->isSuccessful() && $result->getErrorCode() === 'HTTP_ERROR' && class_exists('SoapClient')) {
            $soapResult = $this->sendViaSoap($to, $bodyId, $text);
            if ($soapResult->isSuccessful()) {
                return $soapResult;
            }
        }

        return $result;
    }

    /**
     * Send via Melipayamak BaseServiceNumber REST endpoint.
     */
    private function sendViaRest(string $to, int $bodyId, string $text): SMSResult {
        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'text'     => $text,
            'to'       => $to,
            'bodyId'   => $bodyId,
        ];

        $response = wp_remote_post(self::REST_ENDPOINT, [
            'method'      => 'POST',
            'timeout'     => 15,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            'body'        => http_build_query($data),
            'sslverify'   => $this->sslVerify,
        ]);

        if (is_wp_error($response)) {
            return SMSResult::failure(
                'HTTP_ERROR',
                sprintf(
                    /* translators: %s: Error message */
                    __('خطا در برقراری ارتباط با وب‌سرویس ملی پیامک: %s', 'arma-panel'),
                    $response->get_error_message()
                )
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = trim(wp_remote_retrieve_body($response));

        if ($statusCode !== 200 || empty($body)) {
            return SMSResult::failure(
                'HTTP_STATUS_' . $statusCode,
                sprintf(__('پاسخ نامعتبر از وب‌سرویس ملی پیامک (کد وضعیت: %d)', 'arma-panel'), $statusCode)
            );
        }

        return $this->parseResponse($body);
    }

    /**
     * Send via Melipayamak SOAP WebService (SendByBaseNumber2).
     */
    private function sendViaSoap(string $to, int $bodyId, string $text): SMSResult {
        try {
            ini_set('soap.wsdl_cache_enabled', '0');
            $client = new SoapClient(self::SOAP_WSDL, [
                'encoding'           => 'UTF-8',
                'connection_timeout' => 15,
                'exceptions'         => true,
            ]);

            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'text'     => $text,
                'to'       => $to,
                'bodyId'   => $bodyId,
            ];

            $response = $client->SendByBaseNumber2($data);
            $rawResult = $response->SendByBaseNumber2Result ?? '';

            return $this->parseResponse((string) $rawResult);
        } catch (Throwable $e) {
            return SMSResult::failure(
                'SOAP_EXCEPTION',
                sprintf(__('خطای ارتباط SOAP ملی پیامک: %s', 'arma-panel'), $e->getMessage())
            );
        }
    }

    /**
     * Parse and map Melipayamak API responses.
     */
    private function parseResponse(string $raw): SMSResult {
        // Melipayamak REST may return JSON: {"Value":"recId","RetStatus":1,"StrRetStatus":"Ok"}
        // or a raw numeric string like "2456789123456789" or "0" / "-4"
        $json = json_decode($raw, true);

        if (is_array($json)) {
            $retStatus = (int) ($json['RetStatus'] ?? -999);
            $value     = (string) ($json['Value'] ?? '');

            // RetStatus 1 is Ok in Melipayamak REST
            if ($retStatus === 1 && strlen($value) >= 10 && ctype_digit($value)) {
                return SMSResult::success($value, __('پیامک با موفقیت ارسال شد.', 'arma-panel'));
            }

            $code = $value !== '' ? $value : (string) $retStatus;
            return SMSResult::failure($code, $this->translateErrorCode($code));
        }

        // Raw scalar response (e.g. from SOAP or plain REST)
        $clean = trim($raw, '"\'');

        // Check if recId is a long positive integer (> 10-15 digits per official docs)
        if (strlen($clean) >= 10 && ctype_digit($clean) && (float) $clean > 1000000000) {
            return SMSResult::success($clean, __('پیامک با موفقیت ارسال شد.', 'arma-panel'));
        }

        return SMSResult::failure($clean, $this->translateErrorCode($clean));
    }

    /**
     * Translate Melipayamak return code according to official documentation.
     */
    private function translateErrorCode(string $code): string {
        $map = [
            '0'    => __('نام کاربری یا رمز عبور پنل پیامک صحیح نمی‌باشد.', 'arma-panel'),
            '2'    => __('اعتبار ریالی/پیامکی پنل ملی پیامک کافی نمی‌باشد.', 'arma-panel'),
            '6'    => __('سامانه پیامک در حال بروزرسانی می‌باشد.', 'arma-panel'),
            '7'    => __('متن حاوی کلمه فیلتر شده می‌باشد.', 'arma-panel'),
            '10'   => __('کاربر پنل پیامک فعال نمی‌باشد.', 'arma-panel'),
            '11'   => __('پیامک ارسال نشد.', 'arma-panel'),
            '12'   => __('مدارک کاربر در سامانه پیامک کامل نمی‌باشد.', 'arma-panel'),
            '16'   => __('شماره گیرنده‌ای یافت نشد.', 'arma-panel'),
            '17'   => __('متن پیامک خالی می‌باشد.', 'arma-panel'),
            '18'   => __('شماره گیرنده نامعتبر است.', 'arma-panel'),
            '19'   => __('از محدودیت ساعتی یا روزانه ارسال پیامک فراتر رفته‌اید.', 'arma-panel'),
            '35'   => __('شماره گیرنده در لیست سیاه مخابرات قرار دارد.', 'arma-panel'),
            '-1'   => __('دسترسی استفاده از این وب‌سرویس غیرفعال است.', 'arma-panel'),
            '-2'   => __('محدودیت تعداد شماره (هر بار تنها یک شماره مجاز است).', 'arma-panel'),
            '-3'   => __('خط ارسالی در سیستم تعریف نشده است.', 'arma-panel'),
            '-4'   => __('کد متن پترن (Body ID) ارسالی صحیح نمی‌باشد یا توسط مدیر سامانه تایید نشده است.', 'arma-panel'),
            '-5'   => __('متن ارسالی با متغیرهای مشخص شده در متن پیش‌فرض همخوانی ندارد.', 'arma-panel'),
            '-6'   => __('خطای داخلی در سامانه پیامک رخ داده است.', 'arma-panel'),
            '-7'   => __('خطایی در شماره فرستنده رخ داده است.', 'arma-panel'),
            '-10'  => __('در میان متغیرهای ارسالی، لینک وجود دارد که مجاز نمی‌باشد.', 'arma-panel'),
            '-108' => __('آی‌پی سرور به دلیل تلاش‌های ناموفق مکرر در سامانه پیامک مسدود شده است.', 'arma-panel'),
            '-109' => __('الزام تنظیم IP مجاز در پنل ملی پیامک برای استفاده از API.', 'arma-panel'),
            '-110' => __('الزام استفاده از API Key به جای رمز عبور در پنل پیامک.', 'arma-panel'),
            '-111' => __('آی‌پی درخواست‌کننده در پنل پیامک تعریف نشده یا معتبر نیست.', 'arma-panel'),
        ];

        return $map[$code] ?? sprintf(__('خطای ناشناخته در ارسال پیامک (کد خطا: %s)', 'arma-panel'), esc_html($code));
    }
}
