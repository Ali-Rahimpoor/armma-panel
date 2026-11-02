<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Arma\ArmaPanel\Support\Phone\PhoneService;
use WC_Customer;

/**
 * Address Service handling customer billing and shipping addresses.
 */
class AddressService {

    private PhoneService $phoneService;

    public function __construct(?PhoneService $phoneService = null) {
        $this->phoneService = $phoneService ?? new PhoneService();
    }

    /**
     * Get billing and shipping addresses for user.
     *
     * @param int $userId
     * @return array<string, array<string, string>>
     */
    public function getAddresses(int $userId): array {
        if ($userId <= 0 || !class_exists('WC_Customer')) {
            return ['billing' => [], 'shipping' => []];
        }

        $customer = new WC_Customer($userId);

        return [
            'billing'  => [
                'first_name' => $customer->get_billing_first_name(),
                'last_name'  => $customer->get_billing_last_name(),
                'company'    => $customer->get_billing_company(),
                'address_1'  => $customer->get_billing_address_1(),
                'address_2'  => $customer->get_billing_address_2(),
                'city'       => $customer->get_billing_city(),
                'state'      => $customer->get_billing_state(),
                'postcode'   => $customer->get_billing_postcode(),
                'country'    => $customer->get_billing_country() ?: 'IR',
                'phone'      => $customer->get_billing_phone(),
            ],
            'shipping' => [
                'first_name' => $customer->get_shipping_first_name(),
                'last_name'  => $customer->get_shipping_last_name(),
                'company'    => $customer->get_shipping_company(),
                'address_1'  => $customer->get_shipping_address_1(),
                'address_2'  => $customer->get_shipping_address_2(),
                'city'       => $customer->get_shipping_city(),
                'state'      => $customer->get_shipping_state(),
                'postcode'   => $customer->get_shipping_postcode(),
                'country'    => $customer->get_shipping_country() ?: 'IR',
            ],
        ];
    }

    /**
     * Update address for customer.
     *
     * address_2 is saved for BOTH address types, and the phone
     * number is saved for the BILLING address only — normalized, validated
     * and uniqueness-checked through PhoneService (a phone change must never
     * silently rebind a number that belongs to another account).
     *
     * @param int $userId
     * @param string $type 'billing' or 'shipping'
     * @param array<string, string> $data
     * @return array{success: bool, message: string}
     */
    public function updateAddress(int $userId, string $type, array $data): array {
        if ($userId <= 0 || !class_exists('WC_Customer')) {
            return [
                'success' => false,
                'message' => __('خطا در احراز هویت مشتری.', 'arma-panel'),
            ];
        }

        if ($type !== 'billing' && $type !== 'shipping') {
            return [
                'success' => false,
                'message' => __('نوع نشانی نامعتبر است.', 'arma-panel'),
            ];
        }

        /*
         * the phone field only exists on the BILLING address.
         * Validate it BEFORE touching the customer so a bad number never
         * half-saves.
         */
        $normalizedPhone = null;
        if ($type === 'billing' && array_key_exists('phone', $data)) {
            $normalizedPhone = $this->phoneService->normalize((string) $data['phone']);

            if (!$this->phoneService->validate($normalizedPhone)) {
                return [
                    'success' => false,
                    'message' => __('شماره موبایل وارد شده معتبر نمی‌باشد. لطفاً یک شماره موبایل معتبر ایران وارد کنید.', 'arma-panel'),
                ];
            }

            // Uniqueness: the number must not be bound to ANOTHER account.
            if (!$this->phoneService->isUnique($normalizedPhone, $userId)) {
                return [
                    'success' => false,
                    'message' => __('این شماره موبایل به حساب کاربری دیگری متعلق دارد.', 'arma-panel'),
                ];
            }
        }

        $customer = new WC_Customer($userId);

        if ($type === 'billing') {
            if (isset($data['first_name'])) $customer->set_billing_first_name(sanitize_text_field($data['first_name']));
            if (isset($data['last_name']))  $customer->set_billing_last_name(sanitize_text_field($data['last_name']));
            if (isset($data['company']))    $customer->set_billing_company(sanitize_text_field($data['company']));
            if (isset($data['address_1']))  $customer->set_billing_address_1(sanitize_text_field($data['address_1']));
            if (isset($data['address_2']))  $customer->set_billing_address_2(sanitize_text_field($data['address_2']));
            if (isset($data['city']))       $customer->set_billing_city(sanitize_text_field($data['city']));
            if (isset($data['state']))      $customer->set_billing_state(sanitize_text_field($data['state']));
            if (isset($data['postcode']))   $customer->set_billing_postcode(sanitize_text_field($data['postcode']));
            if (isset($data['country']))    $customer->set_billing_country(sanitize_text_field($data['country']));
            if ($normalizedPhone !== null) {
                $customer->set_billing_phone($normalizedPhone);

                // Keep the authoritative arma_phone binding in sync.
                update_user_meta($userId, PhoneService::META_KEY, $normalizedPhone);
            }
        } else {
            if (isset($data['first_name'])) $customer->set_shipping_first_name(sanitize_text_field($data['first_name']));
            if (isset($data['last_name']))  $customer->set_shipping_last_name(sanitize_text_field($data['last_name']));
            if (isset($data['company']))    $customer->set_shipping_company(sanitize_text_field($data['company']));
            if (isset($data['address_1']))  $customer->set_shipping_address_1(sanitize_text_field($data['address_1']));
            if (isset($data['address_2']))  $customer->set_shipping_address_2(sanitize_text_field($data['address_2']));
            if (isset($data['city']))       $customer->set_shipping_city(sanitize_text_field($data['city']));
            if (isset($data['state']))      $customer->set_shipping_state(sanitize_text_field($data['state']));
            if (isset($data['postcode']))   $customer->set_shipping_postcode(sanitize_text_field($data['postcode']));
            if (isset($data['country']))    $customer->set_shipping_country(sanitize_text_field($data['country']));
        }

        $customer->save();

        return [
            'success' => true,
            'message' => __('نشانی با موفقیت بروزرسانی شد.', 'arma-panel'),
        ];
    }
}
