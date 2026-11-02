<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Core;

use Arma\ArmaPanel\Support\Security\CredentialCrypter;

/**
 * Central Settings Manager.
 * Prevents scattered get_option/update_option calls across the codebase.
 */
class SettingsManager {

    public const OPTION_NAME = 'arma_settings';

    /**
     * In-memory cache of loaded settings.
     *
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    /**
     * Return default plugin configuration.
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array {
        return [
            'general' => [
                'panel_slug'          => 'arma-panel',
                'panel_title'         => 'حساب کاربری من',
                // DANGEROUS, off by default: full data wipe on uninstall
                // (tables, options, user meta, order meta). Cron/transients
                // are always cleaned; this flag gates everything else.
                'delete_data_on_uninstall' => false,
            ],
            'appearance' => [
                'logo_id'           => 0,
                'font_family'       => 'inherit', // 'inherit', 'vazirmatn', 'iranyekan', 'yekanbakh', 'shabnam', 'custom'
                'login_font_enabled'=> true,      // local Vazirmatn on the OTP login page only
                'custom_font_name'  => '',
                'custom_font_url'   => '',

                // Global colors
                'primary_color'     => '#e11d48',
                'secondary_color'   => '#475569',
                'text_color'        => '#1e293b',
                'heading_color'     => '#0f172a',
                'background_color'  => '#f4f5f8', // soft blue-gray canvas (v1.9)

                // Sidebar (نوار کناری)
                'sidebar_bg'           => '#ffffff',
                'sidebar_text'         => '#475569',
                'sidebar_hover_bg'     => '#f8fafc',
                'sidebar_border'       => '#f1f5f9',

                // Cards & inputs
                'card_color'        => '#ffffff',
                'border_color'      => '#f1f5f9',
                'welcome_bg'        => '#f1ece2', // warm welcome-card surface (v1.9)
                'card_radius'       => 16,
                'input_bg'          => '#ffffff',
                'input_border'      => '#cbd5e1',
                'input_radius'      => 12,

                // Buttons
                'button_text'       => '#ffffff',
                'button_radius'     => 12,

                // Status colors
                'success_color'     => '#10b981',
                'warning_color'     => '#f59e0b',
                'danger_color'      => '#ef4444',
            ],
            'dashboard' => [
                // Builder (v1.6.0): order + visibility of dashboard blocks
                'sections' => [
                    ['id' => 'welcome',         'enabled' => true],
                    ['id' => 'stats',           'enabled' => true],
                    ['id' => 'quick',           'enabled' => true],
                    ['id' => 'recent_orders',   'enabled' => true],
                    ['id' => 'frequent_orders', 'enabled' => true],
                ],
                // Welcome card texts ({name} = first name / display name)
                'welcome_title'    => '{name} عزیز، خوش آمدید 🌸',
                'welcome_subtitle' => 'از اینجا می‌توانید سفارش‌ها، نشانی‌ها و سایر جزئیات حساب خود را مدیریت کنید.',
                'welcome_cta_text' => 'مشاهده فروشگاه',
                'welcome_chips'    => true,
                // Stats tiles: columns (2..4) + which tiles are shown
                'stats_columns'    => 4,
                'stats_show'       => ['orders_total', 'orders_active', 'tickets_open', 'unread'],
                // Quick actions: visibility + editable title/desc per item
                'quick_items'      => [
                    'orders'    => ['enabled' => true, 'title' => 'سفارش‌های من',  'desc' => 'پیگیری و مشاهده جزئیات'],
                    'addresses' => ['enabled' => true, 'title' => 'نشانی‌های من', 'desc' => 'مدیریت آدرس‌های ارسال'],
                    'tickets'   => ['enabled' => true, 'title' => 'پشتیبانی',     'desc' => 'ارسال تیکت و پیگیری'],
                    'account'   => ['enabled' => true, 'title' => 'جزئیات حساب',  'desc' => 'پروفایل و شخصی‌سازی'],
                ],
                // Orders cards model: side-by-side two-column or full-width stacked
                'cards_layout'        => 'side', // 'side' | 'stacked'
                'recent_orders_title'   => 'آخرین سفارش‌های من',
                'recent_orders_count'   => 4,
                'frequent_orders_title' => 'پر تکرار ترین سفارش‌های من',
                'frequent_orders_count' => 3,

                // Mobile-first layout (v1.7.0): the phone view has its OWN
                // configuration — it never silently inherits desktop choices.
                'mobile' => [
                    // Menu model: 'toggle' = accordion button (default),
                    //             'open'   = always expanded, no button
                    'menu_model'      => 'toggle',
                    // Profile card: 'full' (default avatar+name+phone+bell),
                    //               'minimal' (name+bell, no account icon),
                    //               'hidden' (removed entirely)
                    'profile_card'    => 'full',
                    // Independent block order + visibility for phones
                    'sections'        => [
                        ['id' => 'welcome',         'enabled' => true],
                        ['id' => 'stats',           'enabled' => true],
                        ['id' => 'quick',           'enabled' => true],
                        ['id' => 'recent_orders',   'enabled' => true],
                        ['id' => 'frequent_orders', 'enabled' => true],
                    ],
                    'stats_columns'   => 2,    // 1 | 2
                    'quick_columns'   => 2,    // 1 | 2
                    'quick_show_desc' => false, // descriptions hidden on phones by default
                ],
            ],
            'wishlist' => [
                'enabled'          => true,
                'button_position'  => 'after_cart', // after_cart|before_cart|after_title|after_price|shortcode
                'button_style'     => 'button',         // 'button' (full) | 'icon' (small heart only)
                'button_text'      => 'افزودن به علاقه‌مندی‌ها',
                'heart_color'      => '#e11d48',
                'guest_behavior'   => 'login',      // 'login' | 'hide'
                'page_title'       => 'علاقه‌مندی‌های من',
                'show_price'       => true,
                'show_add_to_cart' => true,
                'columns'          => 3,             // 2..4
            ],
            'modules' => [
                'wishlist'      => true,
                'tickets'       => true,
                'notifications' => true,
                'order_sms'     => true,
                'lucky_wheel'   => true,
                'downloads'     => false,
            ],
            'woocommerce' => [
                // When true, guests must log in (OTP) on cart/checkout before
                // purchasing; their cart is preserved across the login flow.
                'force_login_checkout' => false,
            ],
            'authentication' => [
                'otp_length'        => 5,
                'otp_expiry'        => 120, // seconds
                'otp_resend_delay'  => 60,  // seconds
                'max_attempts'      => 5,
                'otp_pattern'       => 0,   // Pattern code for OTP SMS
                'smsir_otp_params' => '',   // SMS.ir OTP template parameter names (e.g. Code)
                'faraz_otp_params' => '',   // FarazSMS OTP pattern variable names (e.g. code)
                'ip_rate_max'       => 5,   // global per-IP OTP request cap (anti SMS-credit drain)
                'ip_rate_window'    => 600, // sliding window seconds (10 min)
                // only these roles may log in via OTP (privileged
                // roles are hard-blocked regardless of this list).
                'otp_allowed_roles' => ['customer', 'subscriber'],
                // verify-endpoint IP throttle (default 20 / 10 min).
                'verify_ip_max'     => 20,
                'verify_ip_window'  => 600,
                // Trust X-Forwarded-For / CF-Connecting-IP as the client IP.
                // OFF by default: the header can be forged unless a trusted
                // proxy (CDN/load balancer) overwrites it for every request.
                'trusted_proxy'     => false,
            ],
            'sms' => [
                'provider'         => 'melipayamak',
                'username'         => '',
                'password'         => '',
                'kavenegar_api_key'=> '',   // Kavenegar REST API-Key (path auth)
                'smsir_api_key'   => '',   // SMS.ir X-API-KEY header
                'faraz_api_key'   => '',   // FarazSMS Api-Key header (case-sensitive!)
                'faraz_line_number'=> '',  // FarazSMS sender line number
            ],
            'wc_sms' => [
                'enabled'              => true,
                'order_pattern'        => 0,      // Pattern code for order notification
                'trigger_status'       => 'processing', // 'processing' or 'completed'
                'shipping_sms_enabled' => true,   // send tracking SMS (module must be on too)
                'shipping_pattern'     => 0,      // Pattern: «... توسط {0} ارسال شد ، کد رهگیری {1}»
                'tracking_code_pattern'=> 0,      // Pattern (method empty): «... کد رهگیری {0}»
                // named-parameter providers (SMS.ir / FarazSMS): variable names
                // per message type, comma separated, in send order
                'smsir_order_params'   => '',
                'smsir_shipping_params'=> '',
                'smsir_tracking_params'=> '',
                'smsir_abandoned_params'=> '',
                'faraz_order_params'   => '',
                'faraz_shipping_params'=> '',
                'faraz_tracking_params'=> '',
                'faraz_abandoned_params'=> '',

                // Abandoned cart reminder (24h) for registered customers
                'abandoned_enabled'    => true,
                'abandoned_enabled_at' => 0,      // v1.8.0: activation clock — reminders only count orders created AFTER this moment
                'abandoned_percent'    => 10,     // discount percent for the reminder coupon
                'abandoned_expiry_days'=> 7,
                'abandoned_remind_hours' => 24,   // hours before abandoned-cart/pending-order reminder (admin-configurable)
                'abandoned_cooldown_days' => 30,   // v1.10.2: min gap between two reminders for the same customer
                'abandoned_pattern'    => 0,      // Pattern: «... کد تخفیف {0} ...» ({1} = percent)
                // sweep targets PENDING only; failed/on-hold are opt-in.
                'abandoned_include_failed' => false,
                'abandoned_include_onhold' => false,
            ],
            'lucky_wheel' => [
                'enabled'              => true,
                // Register incentive banner
                'show_banner'          => true,
                'banner_text'          => '🎁 با ثبت‌نام در سایت، پس از هر خرید موفق، گردونه شانس و جوایز ویژه به شما تعلق می‌گیرد!',
                'banner_btn_text'      => 'ورود / ثبت‌نام با موبایل',
                'banner_color1'        => '#0f172a',
                'banner_color2'        => '#881337',
                'banner_cta_color'     => '#fbbf24',
                'banner_cta_text_color'=> '#451a03',   // CTA button TEXT color (admin-configurable)
                // Generate a unique SINGLE-USE WooCommerce coupon per winner
                // (fixes the shared/static coupon bug: codes become personal)
                'unique_coupons'       => true,
                'coupon_prefix'        => 'ARMA',
                'coupon_expiry_days'   => 90,
                'title'                => 'گردونه شانس مشتریان',
                'subtitle'             => 'با تشکر از سفارش شما! شانس خود را برای دریافت جوایز ویژه و کدهای تخفیف امتحان کنید.',
                'slices'               => [
                    [
                        'id'            => 1,
                        'label'         => 'کد تخفیف ۱۰٪',
                        'type'          => 'coupon',
                        'amount'        => 10,
                        'discount_type' => 'percent',
                        'chance'        => 2,
                        'color'         => '#f43f5e',
                    ],
                    [
                        'id'            => 2,
                        'label'         => 'متاسفیم! پوچ شد',
                        'type'          => 'empty',
                        'amount'        => 0,
                        'discount_type' => 'percent',
                        'chance'        => 50,
                        'color'         => '#64748b',
                    ],
                    [
                        'id'            => 3,
                        'label'         => 'کد تخفیف ۵٪',
                        'type'          => 'coupon',
                        'amount'        => 5,
                        'discount_type' => 'percent',
                        'chance'        => 10,
                        'color'         => '#10b981',
                    ],
                    [
                        'id'             => 4,
                        'label'          => 'ارسال رایگان خرید بعد',
                        'type'           => 'coupon',
                        'amount'         => 0,
                        'discount_type'  => 'fixed_cart',
                        'free_shipping'  => true,
                        'chance'         => 3,
                        'color'          => '#0ea5e9',
                    ],
                    [
                        'id'            => 5,
                        'label'         => 'شانس مجدد در خرید بعد',
                        'type'          => 'empty',
                        'amount'        => 0,
                        'discount_type' => 'percent',
                        'chance'        => 35,
                        'color'         => '#94a3b8',
                    ],
                ],
            ],
            'tickets' => [
                'allow_customer_close' => false,
            ],
            'notifications' => [
                'show_bell' => true,
            ],
        ];
    }

    /**
     * Load settings from database merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $saved = get_option(self::OPTION_NAME, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $defaults = $this->getDefaults();
        $this->settings = $this->mergeArrays($defaults, $saved);

        return $this->settings;
    }

    /**
     * Get a setting value using dot notation (e.g. 'appearance.primary_color').
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        $settings = $this->all();
        $segments = explode('.', $key);

        $current = $settings;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        // Transparently decrypt stored credentials (legacy plaintext passes through)
        if (is_string($current) && in_array($key, self::ENCRYPTED_KEYS, true)) {
            return CredentialCrypter::decrypt($current);
        }

        return $current;
    }

    /**
     * Are any stored SMS credentials unreadable with the site's CURRENT
     * encryption key? Happens when wp-config salts changed or the DB was
     * migrated to another site: the values are still "arma1:"-encrypted,
     * but decryption fails (fail closed → ''). The admin must re-enter them.
     */
    public function hasUndecryptableCredential(): bool {
        foreach (self::ENCRYPTED_KEYS as $dotKey) {
            $raw = $this->rawStoredValue($dotKey);

            if (is_string($raw) && $raw !== ''
                && CredentialCrypter::isEncrypted($raw)
                && CredentialCrypter::decrypt($raw) === ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Walk a dot-notation key over the MERGED (raw, undecrypted) settings.
     *
     * @return mixed
     */
    private function rawStoredValue(string $dotKey) {
        $current = $this->all();

        foreach (explode('.', $dotKey) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a setting value using dot notation.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, $value): void {
        $settings = $this->all();
        $segments = explode('.', $key);

        $current = &$settings;
        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        $current = $value;

        $this->settings = $settings;
    }

    /**
     * Check if a module is enabled.
     *
     * @param string $moduleId
     * @return bool
     */
    public function isModuleEnabled(string $moduleId): bool {
        // Mandatory modules are always enabled
        if (in_array($moduleId, ['authentication', 'customer_panel', 'otp', 'woocommerce'], true)) {
            return true;
        }

        return (bool) $this->get("modules.{$moduleId}", true);
    }

    /**
     * Save settings to database.
     *
     * @param array<string, mixed>|null $newSettings
     * @return bool
     */
    /** Credential keys stored encrypted at rest (SMS panel login). */
    private const ENCRYPTED_KEYS = ['sms.username', 'sms.password', 'sms.kavenegar_api_key', 'sms.smsir_api_key', 'sms.faraz_api_key'];

    public function save(?array $newSettings = null): bool {
        if ($newSettings !== null) {
            $this->settings = $this->mergeArrays($this->getDefaults(), $newSettings);
        }

        // Encrypt service credentials at rest (AES-256-GCM, salt-derived key).
        foreach (self::ENCRYPTED_KEYS as $dotKey) {
            $value = $this->get($dotKey, '');
            if (is_string($value) && $value !== '') {
                $this->set($dotKey, CredentialCrypter::encrypt($value));
            }
        }

        return update_option(self::OPTION_NAME, $this->all(), true);
    }

    /**
     * Recursively merge default settings with saved settings.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $saved
     * @return array<string, mixed>
     */
    private function mergeArrays(array $defaults, array $saved): array {
        $result = $defaults;
        foreach ($saved as $key => $value) {
            // Lists (sequential numeric arrays, e.g. dashboard.sections or
            // stats_show) must FULLY replace their defaults — a key-by-key
            // merge would resurrect dropped default entries (v1.6.0 fix).
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])
                && !$this->isArrayList($value) && !$this->isArrayList($result[$key])) {
                $result[$key] = $this->mergeArrays($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * True for sequential numeric-keyed arrays (lists). Saved lists always
     * replace the defaults entirely instead of being merged key-by-key.
     */
    private function isArrayList(array $arr): bool {
        $expected = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $expected++) {
                return false;
            }
        }
        return true;
    }
}
