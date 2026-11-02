<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\OTP;

/**
 * Generates cryptographically secure numeric OTP codes.
 */
class OTPGenerator {

    /**
     * Generate numeric code of given length.
     *
     * @param int $length Number of digits (default 5).
     * @return string
     */
    public function generate(int $length = 5): string {
        $length = max(4, min(8, $length));
        $min = (int) ('1' . str_repeat('0', $length - 1));
        $max = (int) str_repeat('9', $length);

        return (string) random_int($min, $max);
    }

    /**
     * Hash the plain OTP code before saving to database.
     *
     * @param string $code
     * @return string
     */
    public function hash(string $code): string {
        return password_hash($code, PASSWORD_BCRYPT);
    }
}
