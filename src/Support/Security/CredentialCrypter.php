<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Support\Security;

/**
 * Reversible encryption for service credentials (SMS panel username/password).
 *
 * These values must be readable again when talking to the SMS webservice, so a
 * one-way hash is not an option. We encrypt with AES-256-GCM using a key derived
 * from the site's wp-config authentication salts (AUTH_KEY / SECURE_AUTH_KEY) —
 * an attacker who only obtains a database dump cannot decrypt them without also
 * compromising the site's filesystem.
 *
 * Storage format: "arma1:" + base64( iv[12] . tag[16] . ciphertext ).
 * Values without the prefix are treated as legacy plaintext and returned as-is,
 * so existing installs keep working and get re-encrypted on the next save.
 */
final class CredentialCrypter {

    private const PREFIX = 'arma1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    public static function isAvailable(): bool {
        return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    /**
     * Encrypt a plaintext credential. Transparently passes through empty
     * values and anything that is already encrypted.
     */
    public static function encrypt(string $plain): string {
        if ($plain === '' || self::isEncrypted($plain) || !self::isAvailable()) {
            return $plain;
        }

        try {
            $iv  = random_bytes(self::IV_LEN);
            $tag = '';
            $ct  = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

            if ($ct === false) {
                return $plain;
            }

            return self::PREFIX . base64_encode($iv . $tag . $ct);
        } catch (\Throwable $e) {
            return $plain;
        }
    }

    /**
     * Decrypt a stored credential. Legacy plaintext comes back unchanged;
     * tampered/corrupt ciphertext yields '' (fail closed).
     */
    public static function decrypt(string $value): string {
        if ($value === '' || !self::isEncrypted($value) || !self::isAvailable()) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            return '';
        }

        $iv  = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ct  = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    public static function isEncrypted(string $value): bool {
        return strpos($value, self::PREFIX) === 0;
    }

    /**
     * Key derived from the site's wp-config authentication salts. Defined at
     * runtime by WordPress; a deterministic fallback keeps CLI/test tooling
     * working (never used on a real site where the salts exist).
     */
    private static function key(): string {
        $authKey    = defined('AUTH_KEY') ? (string) AUTH_KEY : 'arma-panel-cli-fallback';
        $secureKey  = defined('SECURE_AUTH_KEY') ? (string) SECURE_AUTH_KEY : '';

        return hash('sha256', $authKey . '|' . $secureKey . '|arma-panel-credentials', true);
    }
}
