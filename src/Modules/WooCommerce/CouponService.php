<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Throwable;

/**
 * Coupon Service — creates personal, single-use WooCommerce coupons.
 *
 * Used by the Lucky Wheel prizes and the abandoned-cart reminder. Every code
 * is unique, bound to one user, single-use and expiring — never shared.
 */
class CouponService {

    /**
     * Generate a unique personal coupon.
     *
     * @param string $prefix       e.g. 'ARMA' (uppercase alnum)
     * @param float  $amount       percent (0-100) or cart amount
     * @param string $discountType 'percent' | 'fixed_cart'
     * @param int    $expiryDays   0 = never expires
     * @param int    $userId       restrict to this user (0 = no restriction)
     * @param string $description  internal note
     * @param bool   $freeShipping also grant free shipping
     * @return string coupon code, or '' when generation was impossible
     */
    public static function generate(
        string $prefix,
        float $amount,
        string $discountType,
        int $expiryDays,
        int $userId,
        string $description,
        bool $freeShipping = false
    ): string {
        if (!class_exists('WC_Coupon') || !function_exists('wc_get_coupon_id_by_code')) {
            self::log('WooCommerce coupon API unavailable.');

            return '';
        }

        try {
            $prefix = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $prefix) ?? '');
            $prefix = $prefix !== '' ? $prefix : 'ARMA';

            $code = '';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $random = function_exists('wp_generate_password')
                    ? wp_generate_password(8, false)
                    : bin2hex(random_bytes(4));
                $candidate = $prefix . '-' . strtoupper($random);
                $candidate = preg_replace('/[^A-Z0-9\-]/', '', $candidate) ?? $candidate;

                if ((int) wc_get_coupon_id_by_code($candidate) === 0) {
                    $code = $candidate;
                    break;
                }
            }

            if ($code === '') {
                return '';
            }

            $type = $discountType === 'fixed_cart' ? 'fixed_cart' : 'percent';

            $coupon = new \WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($type);
            $coupon->set_amount($amount);
            $coupon->set_individual_use(true);       // cannot be combined
            $coupon->set_usage_limit(1);             // single use overall
            $coupon->set_usage_limit_per_user(1);    // single use per user
            $coupon->set_free_shipping($freeShipping);

            if ($expiryDays > 0) {
                $daySeconds = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
                $coupon->set_date_expires(time() + ($expiryDays * $daySeconds));
            }

            $coupon->set_description($description);
            $coupon->save();

            /*
             * bind the coupon to its WINNER. The winner's user id
             * is stored in coupon meta and `woocommerce_coupon_is_valid`
             * (hooked in WooCommerceManager) rejects anyone else trying to
             * redeem the code ("این کد مخصوص شما نیست").
             *
             * NOTE: we deliberately do NOT use email restrictions here.
             * Arma accounts carry a synthetic internal email (never shown,
             * never used at checkout), so an email restriction would make
             * WooCommerce reject an otherwise valid prize coupon the moment
             * the customer fills their real billing email. Personal binding is
             * enforced by: individual_use + usage_limit(1) + per-user limit(1)
             * + the owner check + the code being handed only to its winner
             * inside their panel.
             */
            if ($userId > 0) {
                $coupon->update_meta_data('_arma_user_id', (int) $userId);
                $coupon->save();
            }

            return $code;
        } catch (Throwable $e) {
            self::log('generation failed: ' . $e->getMessage());

            return '';
        }
    }

    private static function log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ArmaPanel CouponService] ' . $message);
        }
    }
}
