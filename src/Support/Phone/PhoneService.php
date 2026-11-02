<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Support\Phone;

use Arma\ArmaPanel\Core\SettingsManager;
use WP_User;
use wpdb;

/**
 * Centralized Phone Service.
 * Single source of truth for phone normalization, validation, formatting, and user resolution.
 */
class PhoneService {

    public const META_KEY = 'arma_phone';

    private ?SettingsManager $settings;

    public function __construct(?SettingsManager $settings = null) {
        $this->settings = $settings;
    }

    /**
     * Normalize a phone number to standard canonical format: 09xxxxxxxxx (11 digits).
     *
     * @param string|int|null $phone
     * @return string Normalized phone or empty string if invalid input.
     */
    public function normalize($phone): string {
        if ($phone === null) {
            return '';
        }

        $phone = (string) $phone;
        $phone = trim($phone);

        // 1. Convert Persian and Arabic numbers to standard English ASCII numbers
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $phone = str_replace($persianDigits, $englishDigits, $phone);
        $phone = str_replace($arabicDigits, $englishDigits, $phone);

        // 2. Remove all non-digit characters (spaces, dashes, parens, plus, etc.)
        $phone = preg_replace('/\D+/', '', $phone);

        if (empty($phone)) {
            return '';
        }

        // 3. Handle country codes
        // 00989xxxxxxxxx (14 digits) -> 09xxxxxxxxx
        if (str_starts_with($phone, '0098') && strlen($phone) === 14) {
            $phone = '0' . substr($phone, 4);
        }
        // 989xxxxxxxxx (12 digits) -> 09xxxxxxxxx
        elseif (str_starts_with($phone, '98') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 2);
        }
        // 9xxxxxxxxx (10 digits) -> 09xxxxxxxxx
        elseif (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    /**
     * Validate an Iranian mobile phone number.
     *
     * @param string|int|null $phone
     * @return bool
     */
    public function validate($phone): bool {
        $canonical = $this->normalize($phone);

        // Iranian mobile format: 11 digits, starts with 09
        // Pre-numbers: 0901..0905, 0910..0919, 0920..0922, 0930..0939, 0990..0999, etc.
        return (bool) preg_match('/^09[0-9]{9}$/', $canonical);
    }

    /**
     * Format a phone number for UI display.
     *
     * @param string|null $phone
     * @param bool $persianDigits
     * @return string
     */
    public function format(?string $phone, bool $persianDigits = true): string {
        $canonical = $this->normalize($phone);
        if (!$this->validate($canonical)) {
            return (string) $phone;
        }

        // Format: 0912 345 6789
        $formatted = substr($canonical, 0, 4) . ' ' . substr($canonical, 4, 3) . ' ' . substr($canonical, 7, 4);

        if ($persianDigits && function_exists('arma_to_persian_digits')) {
            return arma_to_persian_digits($formatted);
        }

        return $formatted;
    }

    /**
     * All storage formats a legacy phone value may carry for the same
     * canonical 09xxxxxxxxx number (lookups must match every
     * format ever written, without a one-off migration).
     *
     * @return array<int, string>
     */
    private function phoneStorageVariants(string $canonical): array {
        $national = substr($canonical, 1); // 9xxxxxxxxx

        return array_values(array_unique([
            $canonical,                    // 09xxxxxxxxx
            '98' . $national,              // 989xxxxxxxxx
            '+98' . $national,             // +989xxxxxxxxx
            '0098' . $national,            // 00989xxxxxxxxx
            $national,                     // 9xxxxxxxxx
        ]));
    }

    /**
     * Find a WordPress User by normalized phone number.
     *
     * the billing_phone fallback is now strictly guarded —
     *   - it matches ALL storage formats of the number (multi-format query);
     *   - it requires EXACTLY ONE matching account (ambiguous data is a
     *     mis-configuration, not an invitation to log in as someone);
     *   - the matched account must NOT already carry a bound arma_phone
     *     (a number already bound to account A must never resolve to B);
     *   - the account's role must be allowed for OTP login.
     *
     * @param string $phone
     * @return WP_User|null
     */
    public function findUser(string $phone): ?WP_User {
        global $wpdb;

        $canonical = $this->normalize($phone);
        if (!$this->validate($canonical)) {
            return null;
        }

        // 1. Search by arma_phone user meta (the authoritative binding)
        $user_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                self::META_KEY,
                $canonical
            )
        );

        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user instanceof WP_User) {
                return $user;
            }
        }

        /*
         * 2. Search by billing_phone user meta (WooCommerce fallback).
         *    Phones were historically stored in several formats; the query
         *    matches all of them at once, and only an UNAMBIGUOUS single
         *    match on an un-bound, role-allowed account is accepted.
         */
        $variants  = $this->phoneStorageVariants($canonical);
        $placeholders = implode(',', array_fill(0, count($variants), '%s'));

        $sql = "SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = 'billing_phone'
                   AND meta_value IN ({$placeholders})";

        $rows = $wpdb->get_results(
            count($variants) === 1
                ? $wpdb->prepare($sql, $variants[0])
                : $wpdb->prepare($sql, ...$variants)
        );

        if (is_array($rows) && count($rows) === 1) {
            $row     = (object) reset($rows);
            $user_id = (int) ($row->user_id ?? 0);
            $user    = $user_id > 0 ? get_user_by('id', $user_id) : null;

            if ($user instanceof WP_User) {
                // The account must not already have ANOTHER arma_phone bound.
                $bound = get_user_meta($user_id, self::META_KEY, true);
                if (is_string($bound) || is_numeric($bound)) {
                    if (trim((string) $bound) !== '') {
                        return null;
                    }
                }

                // Privileged / non-allowed accounts never resolve via phone.
                if (!$this->hasAllowedOtpRole($user)) {
                    return null;
                }

                // Exactly one unambiguous, allowed match: bind it.
                update_user_meta($user_id, self::META_KEY, $canonical);

                return $user;
            }
        }

        // 3. Search by user_login matching canonical phone
        $user = get_user_by('login', $canonical);
        if ($user instanceof WP_User) {
            update_user_meta($user->ID, self::META_KEY, $canonical);
            return $user;
        }

        return null;
    }

    /**
     * Does the account hold a role that the OTP login flow may touch?
     * (Privileged roles — administrator, shop_manager, editor — and any role
     * outside the admin-configured allowlist are rejected.)
     *
     * (kept in sync with AuthService::isOtpLoginAllowed).
     */
    public function hasAllowedOtpRole(WP_User $user): bool {
        $roles = [];
        if (isset($user->roles) && is_array($user->roles)) {
            $roles = $user->roles;
        } elseif (property_exists($user, 'roles') && is_array($user->roles ?? null)) {
            $roles = (array) $user->roles;
        }

        $privileged = ['administrator', 'shop_manager', 'editor'];
        foreach ($privileged as $role) {
            if (in_array($role, $roles, true)) {
                return false;
            }
        }

        $allowedRoles = $this->settings !== null
            ? (array) $this->settings->get('authentication.otp_allowed_roles', ['customer', 'subscriber'])
            : ['customer', 'subscriber'];
        $allowedRoles = array_values(array_filter(array_map('strval', $allowedRoles)));

        if ($allowedRoles === []) {
            return false;
        }

        foreach ($roles as $role) {
            if (in_array((string) $role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a phone number is unique (not taken by another user).
     *
     * @param string $phone
     * @param int|null $excludeUserId
     * @return bool True if unique / available, false if already taken.
     */
    public function isUnique(string $phone, ?int $excludeUserId = null): bool {
        global $wpdb;

        $canonical = $this->normalize($phone);
        if (!$this->validate($canonical)) {
            return false;
        }

        $sql = "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s";
        $params = [self::META_KEY, $canonical];

        if ($excludeUserId !== null && $excludeUserId > 0) {
            $sql .= " AND user_id != %d";
            $params[] = $excludeUserId;
        }

        $sql .= " LIMIT 1";

        $existing = $wpdb->get_var($wpdb->prepare($sql, ...$params));

        return empty($existing);
    }

    /**
     * Safely assign phone number to a user with uniqueness and race-condition checks.
     *
     * @param int $userId
     * @param string $phone
     * @return bool
     */
    public function assignToUser(int $userId, string $phone): bool {
        $canonical = $this->normalize($phone);
        if (!$this->validate($canonical)) {
            return false;
        }

        if (!$this->isUnique($canonical, $userId)) {
            return false;
        }

        update_user_meta($userId, self::META_KEY, $canonical);
        update_user_meta($userId, 'billing_phone', $canonical);
        update_user_meta($userId, 'arma_phone_verified', 1);

        return true;
    }
}
