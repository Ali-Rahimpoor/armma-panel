<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Admin\Controllers;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\CustomerPanel\PanelRouter;
use Arma\ArmaPanel\Modules\SMS\SMSManager;

/**
 * Controller handling WordPress admin settings pages with professional SaaS UI.
 */
class SettingsController {

    private SettingsManager $settings;
    private SMSManager $smsManager;

    public function __construct(SettingsManager $settings, ?SMSManager $smsManager = null) {
        $this->settings   = $settings;
        $this->smsManager = $smsManager ?? new SMSManager($settings);
    }

    public function setSmsManager(SMSManager $smsManager): void {
        $this->smsManager = $smsManager;
    }

    public function renderSettingsPage(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما دسترسی لازم برای این صفحه را ندارید.', 'arma-panel'));
        }

        $activeTab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
        $saved = false;
        $testSmsNotice = '';
        $chanceErrorNotice = '';

        // Handle Test SMS sending
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arma_send_test_sms'])) {
            check_admin_referer('arma_save_settings', 'arma_settings_nonce');

            $testPhone   = sanitize_text_field(wp_unslash($_POST['test_mobile'] ?? ''));
            $testPattern = sanitize_text_field(wp_unslash($_POST['test_pattern'] ?? ''));

            if (!empty($testPhone)) {
                $testResult = $this->smsManager->sendTestSmsWithPattern($testPhone, $testPattern !== '' ? $testPattern : null);
                if ($testResult->isSuccessful()) {
                    $testSmsNotice = sprintf(
                        '<div class="notice notice-success is-dismissible"><p>%s (شناسه پیگیری: %s)</p></div>',
                        esc_html__('پیامک تست با موفقیت ارسال شد.', 'arma-panel'),
                        esc_html((string)$testResult->getRecId())
                    );
                } else {
                    $testSmsNotice = sprintf(
                        '<div class="notice notice-error is-dismissible"><p>%s: %s</p></div>',
                        esc_html__('خطا در ارسال پیامک تست', 'arma-panel'),
                        esc_html($testResult->getMessage())
                    );
                }
            } else {
                $testSmsNotice = '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('لطفاً شماره موبایل برای تست را وارد کنید.', 'arma-panel') . '</p></div>';
            }
        }
        // Handle Regular Settings save
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arma_settings_nonce'])) {
            check_admin_referer('arma_save_settings', 'arma_settings_nonce');

            $allSettings = $this->settings->all();

            switch ($activeTab) {
                case 'general':
                    $oldSlug = (string) $allSettings['general']['panel_slug'];
                    $newSlug = sanitize_title(wp_unslash($_POST['panel_slug'] ?? 'arma-panel'));

                    $allSettings['general']['panel_slug'] = $newSlug !== '' ? $newSlug : 'arma-panel';
                    $allSettings['general']['panel_title'] = sanitize_text_field(wp_unslash($_POST['panel_title'] ?? ''));
                    $allSettings['woocommerce']['force_login_checkout'] = !empty($_POST['force_login_checkout']);

                    /*
                     * Uninstall data-wipe toggle (checkbox = present only
                     * when on). Off by default: uninstalling without this
                     * flag keeps tables/options/user meta/order meta.
                     */
                    $allSettings['general']['delete_data_on_uninstall'] = !empty($_POST['delete_data_on_uninstall']);

                    // When the slug changes, invalidate the rewrite hash so rules
                    // (and the panel page slug) are re-synced safely on the next
                    // request — flushing here would persist stale-slug rules.
                    if ($oldSlug !== $allSettings['general']['panel_slug']) {
                        PanelRouter::invalidateRewriteHash();
                    }
                    break;

                case 'appearance':
                    $allSettings['appearance']['logo_id']          = (int) ($_POST['logo_id'] ?? 0);
                    $allSettings['appearance']['font_family']      = sanitize_text_field(wp_unslash($_POST['font_family'] ?? 'inherit'));
                    $allSettings['appearance']['custom_font_name']  = sanitize_text_field(wp_unslash($_POST['custom_font_name'] ?? ''));
                    $allSettings['appearance']['custom_font_url']   = esc_url_raw(wp_unslash($_POST['custom_font_url'] ?? ''));
                    $allSettings['appearance']['login_font_enabled']= !empty($_POST['login_font_enabled']);

                    $colorKeys = [
                        'primary_color', 'secondary_color', 'text_color', 'heading_color',
                        'background_color',
                        'sidebar_bg', 'sidebar_text',
                        'sidebar_hover_bg', 'sidebar_border',
                        'card_color', 'border_color', 'input_bg', 'input_border', 'button_text',
                        'success_color', 'warning_color', 'danger_color',
                        'welcome_bg',
                    ];
                    foreach ($colorKeys as $colorKey) {
                        $allSettings['appearance'][$colorKey] = sanitize_hex_color(wp_unslash($_POST[$colorKey] ?? ''))
                            ?: (string) $this->settings->get('appearance.' . $colorKey, '');
                    }

                    $allSettings['appearance']['card_radius']  = max(0, min(40, (int) ($_POST['card_radius'] ?? 16)));
                    $allSettings['appearance']['input_radius'] = max(0, min(40, (int) ($_POST['input_radius'] ?? 12)));
                    $allSettings['appearance']['button_radius']= max(0, min(40, (int) ($_POST['button_radius'] ?? 12)));
                    break;

                case 'authentication':
                    $allSettings['authentication']['otp_length']       = max(4, min(8, (int) ($_POST['otp_length'] ?? 5)));
                    $allSettings['authentication']['otp_expiry']       = max(60, (int) ($_POST['otp_expiry'] ?? 120));
                    $allSettings['authentication']['otp_resend_delay'] = max(30, (int) ($_POST['otp_resend_delay'] ?? 60));
                    $allSettings['authentication']['max_attempts']     = max(3, min(10, (int) ($_POST['max_attempts'] ?? 5)));
                    $allSettings['authentication']['otp_pattern']      = trim(sanitize_text_field(wp_unslash($_POST['otp_pattern'] ?? '')));
                    $allSettings['authentication']['smsir_otp_params'] = trim(sanitize_text_field(wp_unslash($_POST['smsir_otp_params'] ?? '')));
                    $allSettings['authentication']['faraz_otp_params'] = trim(sanitize_text_field(wp_unslash($_POST['faraz_otp_params'] ?? '')));
                        $allSettings['authentication']['ip_rate_max']     = max(0, min(100, (int) ($_POST['ip_rate_max'] ?? 5)));
                    $allSettings['authentication']['ip_rate_window']  = max(60, min(86400, (int) ($_POST['ip_rate_window'] ?? 600)));

                    /*
                     * "نقش‌های مجاز ورود با OTP" — checkboxes
                     * (otp_allowed_roles[]). Privileged roles are ALWAYS
                     * rejected by AuthService regardless of this list.
                     */
                    $allowedRoles = isset($_POST['otp_allowed_roles']) && is_array($_POST['otp_allowed_roles'])
                        ? array_map('sanitize_key', array_map('strval', wp_unslash($_POST['otp_allowed_roles'])))
                        : [];
                    $allowedRoles = array_values(array_unique(array_filter($allowedRoles)));
                    // privileged roles can never be allowed here
                    $allowedRoles = array_values(array_diff($allowedRoles, ['administrator', 'shop_manager', 'editor']));
                    $allSettings['authentication']['otp_allowed_roles'] = $allowedRoles;

                    // verify-endpoint IP throttle (default 20 / 10 min)
                    $allSettings['authentication']['verify_ip_max']    = max(0, min(500, (int) ($_POST['verify_ip_max'] ?? 20)));
                    $allSettings['authentication']['verify_ip_window'] = max(60, min(86400, (int) ($_POST['verify_ip_window'] ?? 600)));

                    /*
                     * Trusted proxy toggle (checkbox = present only when on).
                     * Enabling it makes the OTP layer read the client IP from
                     * X-Forwarded-For / CF-Connecting-IP instead of REMOTE_ADDR.
                     */
                    $allSettings['authentication']['trusted_proxy'] = isset($_POST['trusted_proxy']);
                    break;

                case 'sms':
                    $allSettings['sms']['provider']     = sanitize_text_field(wp_unslash($_POST['sms_provider'] ?? 'melipayamak'));
                    $activeProvider = $allSettings['sms']['provider'];

                    /*
                     * Provider-scoped credentials (only the active provider's fields save).
                     *
                     * ALL credential blocks live in ONE form — the hidden ones
                     * still submit their (empty or browser-autofilled) values.
                     * Saving while another provider is selected must therefore
                     * never touch the other providers' stored credentials:
                     * each block only persists while ITS provider is selected,
                     * and key fields additionally keep the stored value when
                     * left empty (masked inputs never echo the value back).
                     */
                    if ($activeProvider === 'melipayamak') {
                        $allSettings['sms']['username'] = sanitize_text_field(wp_unslash($_POST['sms_username'] ?? ''));
                        $newPass = sanitize_text_field(wp_unslash($_POST['sms_password'] ?? ''));
                        if ($newPass !== '') {
                            $allSettings['sms']['password'] = $newPass;
                        }
                    }

                    if ($activeProvider === 'kavenegar') {
                        $newKvKey = sanitize_text_field(wp_unslash($_POST['kavenegar_api_key'] ?? ''));
                        if ($newKvKey !== '') {
                            $allSettings['sms']['kavenegar_api_key'] = $newKvKey;
                        }
                    }

                    if ($activeProvider === 'smsir') {
                        $newSmsIrKey = sanitize_text_field(wp_unslash($_POST['smsir_api_key'] ?? ''));
                        if ($newSmsIrKey !== '') {
                            $allSettings['sms']['smsir_api_key'] = $newSmsIrKey;
                        }
                    }

                    if ($activeProvider === 'faraz') {
                        $newFarazKey = sanitize_text_field(wp_unslash($_POST['faraz_api_key'] ?? ''));
                        if ($newFarazKey !== '') {
                            $allSettings['sms']['faraz_api_key'] = $newFarazKey;
                        }
                        $allSettings['sms']['faraz_line_number'] = trim(sanitize_text_field(wp_unslash($_POST['faraz_line_number'] ?? '')));
                    }


                    break;

                case 'wc_sms':
                    $allSettings['wc_sms']['enabled']        = !empty($_POST['wc_sms_enabled']);
                    $allSettings['wc_sms']['order_pattern']  = trim(sanitize_text_field(wp_unslash($_POST['order_pattern'] ?? '')));
                    $allSettings['wc_sms']['trigger_status'] = sanitize_text_field(wp_unslash($_POST['trigger_status'] ?? 'processing'));
                    $allSettings['wc_sms']['shipping_sms_enabled'] = !empty($_POST['shipping_sms_enabled']);
                    $allSettings['wc_sms']['shipping_pattern']     = trim(sanitize_text_field(wp_unslash($_POST['shipping_pattern'] ?? '')));
                    $allSettings['wc_sms']['tracking_code_pattern']= trim(sanitize_text_field(wp_unslash($_POST['tracking_code_pattern'] ?? '')));

                    // named-parameter providers (SMS.ir / FarazSMS)
                    foreach (['smsir', 'faraz'] as $pv) {
                        foreach (['order', 'shipping', 'tracking', 'abandoned'] as $ctx) {
                            $field = $pv . '_' . $ctx . '_params';
                            $allSettings['wc_sms'][$field] = trim(sanitize_text_field(wp_unslash($_POST[$field] ?? '')));
                        }
                    }

                    // activation clock: reminders only count orders created
                    // AFTER the feature was (re-)enabled — historical pending
                    // orders never fire a mass SMS on enable. Staying enabled
                    // keeps the original clock; disabling clears it.
                    $wasAbandonedEnabled = !empty($allSettings['wc_sms']['abandoned_enabled']);
                    $nowAbandonedEnabled = !empty($_POST['abandoned_enabled']);
                    $allSettings['wc_sms']['abandoned_enabled'] = $nowAbandonedEnabled;
                    if ($nowAbandonedEnabled && !$wasAbandonedEnabled) {
                        $allSettings['wc_sms']['abandoned_enabled_at'] = time();
                    } elseif (!$nowAbandonedEnabled) {
                        $allSettings['wc_sms']['abandoned_enabled_at'] = 0;
                    }
                    $allSettings['wc_sms']['abandoned_percent']     = max(1, min(100, (int) ($_POST['abandoned_percent'] ?? 10)));
                    $allSettings['wc_sms']['abandoned_remind_hours'] = max(1, min(720, (int) ($_POST['abandoned_remind_hours'] ?? 24)));
                    $allSettings['wc_sms']['abandoned_cooldown_days'] = max(1, min(365, (int) ($_POST['abandoned_cooldown_days'] ?? 30)));
                    $allSettings['wc_sms']['abandoned_expiry_days'] = max(1, min(365, (int) ($_POST['abandoned_expiry_days'] ?? 7)));
                    $allSettings['wc_sms']['abandoned_pattern']     = trim(sanitize_text_field(wp_unslash($_POST['abandoned_pattern'] ?? '')));

                    // optional sweep statuses — pending only by default
                    $allSettings['wc_sms']['abandoned_include_failed'] = !empty($_POST['abandoned_include_failed']);
                    $allSettings['wc_sms']['abandoned_include_onhold'] = !empty($_POST['abandoned_include_onhold']);
                    break;

                case 'lucky_wheel':
                    $allSettings['modules']['lucky_wheel']               = !empty($_POST['lucky_wheel_enabled']);
                    $allSettings['lucky_wheel']['enabled']              = !empty($_POST['lucky_wheel_enabled']);
                    $allSettings['lucky_wheel']['show_banner']          = !empty($_POST['wheel_show_banner']);
                    $allSettings['lucky_wheel']['banner_text']          = sanitize_textarea_field(wp_unslash($_POST['banner_text'] ?? ''));
                    $allSettings['lucky_wheel']['banner_btn_text']      = sanitize_text_field(wp_unslash($_POST['banner_btn_text'] ?? ''));
                    $allSettings['lucky_wheel']['banner_color1']        = sanitize_hex_color(wp_unslash($_POST['banner_color1'] ?? '')) ?: '#0f172a';
                    $allSettings['lucky_wheel']['banner_color2']        = sanitize_hex_color(wp_unslash($_POST['banner_color2'] ?? '')) ?: '#881337';
                    $allSettings['lucky_wheel']['banner_cta_color']     = sanitize_hex_color(wp_unslash($_POST['banner_cta_color'] ?? '')) ?: '#fbbf24';
                    $allSettings['lucky_wheel']['banner_cta_text_color'] = sanitize_hex_color(wp_unslash($_POST['banner_cta_text_color'] ?? '')) ?: '#451a03';
                    $allSettings['lucky_wheel']['unique_coupons']       = true; // always on (static codes removed)
                    $allSettings['lucky_wheel']['coupon_prefix']        = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string) wp_unslash($_POST['coupon_prefix'] ?? 'ARMA'))) ?: 'ARMA';
                    $allSettings['lucky_wheel']['coupon_expiry_days']   = max(0, min(365, (int) ($_POST['coupon_expiry_days'] ?? 90)));
                    $allSettings['lucky_wheel']['title']                = sanitize_text_field(wp_unslash($_POST['wheel_title'] ?? ''));
                    $allSettings['lucky_wheel']['subtitle']             = sanitize_text_field(wp_unslash($_POST['wheel_subtitle'] ?? ''));

                    // Slices parsing (chances are percents of 100;
                    // 0 = displayed but never wins; sum must stay <= 100)
                    if (isset($_POST['slices']) && is_array($_POST['slices'])) {
                        $newSlices = [];
                        $chanceTotal = 0;
                        foreach ($_POST['slices'] as $idx => $sliceData) {
                            $label  = sanitize_text_field(wp_unslash($sliceData['label'] ?? ''));
                            if (empty($label)) continue;
                            $chance = max(0, min(100, (int) ($sliceData['chance'] ?? 0)));
                            $chanceTotal += $chance;
                            // Prize types are تخفیف | پوچ | ارسال رایگان.
                            // Amount/discount type only apply to discounts; the
                            // free-shipping prize is stored as a flag.
                            $sliceType = sanitize_text_field(wp_unslash($sliceData['type'] ?? 'empty'));
                            if (!in_array($sliceType, ['coupon', 'empty', 'free_shipping'], true)) {
                                $sliceType = 'empty';
                            }
                            $newSlices[] = [
                                'id'             => $idx + 1,
                                'label'          => $label,
                                'type'           => $sliceType,
                                'amount'         => $sliceType === 'coupon' ? max(0, (float) ($sliceData['amount'] ?? 0)) : 0,
                                'discount_type'  => $sliceType === 'coupon' && in_array($sliceData['discount_type'] ?? 'percent', ['percent', 'fixed_cart'], true) ? $sliceData['discount_type'] : 'percent',
                                'free_shipping'  => ($sliceType === 'free_shipping'),
                                'chance'         => $chance,
                                'color'          => sanitize_hex_color(wp_unslash($sliceData['color'] ?? '#f43f5e')) ?: '#f43f5e',
                            ];
                        }
                        if ($chanceTotal > 100) {
                            // Over-100 configurations are REJECTED: keep the previous
                            // slices and show the admin exactly what to fix.
                            $chanceErrorNotice = sprintf(
                                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                                esc_html(sprintf(
                                    /* translators: %s: total percent */
                                    __('خطا: مجموع درصد شانس‌های گردونه %s٪ است و از ۱۰۰ بیشتر می‌شود! بخش‌های گردونه ذخیره نشد؛ درصد‌ها را طوری مرتب کنید که حداکثر ۱۰۰ شوند.', 'arma-panel'),
                                    $chanceTotal
                                ))
                            );
                        } elseif (!empty($newSlices)) {
                            $allSettings['lucky_wheel']['slices'] = $newSlices;
                        }
                    }
                    break;

                case 'wishlist':
                    $allSettings['modules']['wishlist']        = !empty($_POST['wishlist_enabled']);
                    $allSettings['wishlist']['enabled']        = !empty($_POST['wishlist_enabled']);
                    $allSettings['wishlist']['button_text']    = sanitize_text_field(wp_unslash($_POST['wishlist_button_text'] ?? ''));
                    $position = sanitize_text_field(wp_unslash($_POST['wishlist_button_position'] ?? 'after_cart'));
                    $allSettings['wishlist']['button_position'] = in_array($position, ['after_cart', 'before_cart', 'after_title', 'after_price', 'shortcode'], true) ? $position : 'after_cart';
                    $style = sanitize_text_field(wp_unslash($_POST['wishlist_button_style'] ?? 'button'));
                    $allSettings['wishlist']['button_style'] = in_array($style, ['button', 'icon'], true) ? $style : 'button';
                    $allSettings['wishlist']['heart_color']     = sanitize_hex_color(wp_unslash($_POST['wishlist_heart_color'] ?? '')) ?: '#e11d48';
                    $guest = sanitize_text_field(wp_unslash($_POST['wishlist_guest_behavior'] ?? 'login'));
                    $allSettings['wishlist']['guest_behavior']  = in_array($guest, ['login', 'hide'], true) ? $guest : 'login';
                    $allSettings['wishlist']['page_title']      = sanitize_text_field(wp_unslash($_POST['wishlist_page_title'] ?? ''));
                    $allSettings['wishlist']['columns']         = max(2, min(4, (int) ($_POST['wishlist_columns'] ?? 3)));
                    $allSettings['wishlist']['show_price']      = !empty($_POST['wishlist_show_price']);
                    $allSettings['wishlist']['show_add_to_cart']= !empty($_POST['wishlist_show_add_to_cart']);
                    break;

                case 'modules':
                    $allSettings['modules']['tickets']       = !empty($_POST['module_tickets']);
                    $allSettings['modules']['notifications'] = !empty($_POST['module_notifications']);
                    $allSettings['modules']['lucky_wheel']   = !empty($_POST['module_lucky_wheel']);
                    $allSettings['modules']['order_sms']     = !empty($_POST['module_order_sms']);
                    $allSettings['modules']['wishlist']     = !empty($_POST['module_wishlist']);
                    break;

                case 'dashboard':
                    // Dashboard builder: block order + visibility
                    $validDashSections = ['welcome', 'stats', 'quick', 'recent_orders', 'frequent_orders'];
                    $dashOrder = [];
                    foreach ((array) ($_POST['dash_sections'] ?? []) as $dashSid) {
                        $dashSid = preg_replace('/[^a-z0-9_]/', '', (string) wp_unslash($dashSid));
                        if (in_array($dashSid, $validDashSections, true) && !in_array($dashSid, $dashOrder, true)) {
                            $dashOrder[] = $dashSid;
                        }
                    }
                    // Safety net: every known block must stay present exactly once
                    foreach ($validDashSections as $dashSid) {
                        if (!in_array($dashSid, $dashOrder, true)) {
                            $dashOrder[] = $dashSid;
                        }
                    }
                    $dashSections = [];
                    foreach ($dashOrder as $dashSid) {
                        $dashSections[] = [
                            'id'      => $dashSid,
                            'enabled' => !empty($_POST['dash_enabled'][$dashSid]),
                        ];
                    }
                    $allSettings['dashboard']['sections'] = $dashSections;

                    // Custom titles (empty value = fall back to the default text)
                    $allSettings['dashboard']['welcome_title']         = sanitize_text_field(wp_unslash($_POST['dash_title']['welcome'] ?? ''));
                    $allSettings['dashboard']['recent_orders_title']   = sanitize_text_field(wp_unslash($_POST['dash_title']['recent_orders'] ?? ''));
                    $allSettings['dashboard']['frequent_orders_title'] = sanitize_text_field(wp_unslash($_POST['dash_title']['frequent_orders'] ?? ''));

                    // Welcome card
                    $allSettings['dashboard']['welcome_subtitle'] = sanitize_textarea_field(wp_unslash($_POST['dash_welcome_subtitle'] ?? ''));
                    $allSettings['dashboard']['welcome_cta_text'] = sanitize_text_field(wp_unslash($_POST['dash_welcome_cta'] ?? ''));
                    $allSettings['dashboard']['welcome_chips']    = !empty($_POST['dash_welcome_chips']);

                    // Stats tiles
                    $allSettings['dashboard']['stats_columns'] = (int) ($_POST['dash_stats_columns'] ?? 4);
                    if ($allSettings['dashboard']['stats_columns'] < 2 || $allSettings['dashboard']['stats_columns'] > 4) {
                        $allSettings['dashboard']['stats_columns'] = 4;
                    }
                    $validStats = ['orders_total', 'orders_active', 'tickets_open', 'unread'];
                    $statsShow = [];
                    foreach ($validStats as $statKey) {
                        if (!empty($_POST['dash_stats_show'][$statKey])) {
                            $statsShow[] = $statKey;
                        }
                    }
                    if (empty($statsShow)) {
                        $statsShow = $validStats; // never allow an empty dashboard stats row
                    }
                    $allSettings['dashboard']['stats_show'] = $statsShow;

                    // Quick actions (per-item visibility + texts)
                    $quickDefaults = [
                        'orders'    => ['title' => 'سفارش‌های من',  'desc' => 'پیگیری و مشاهده جزئیات'],
                        'addresses' => ['title' => 'نشانی‌های من', 'desc' => 'مدیریت آدرس‌های ارسال'],
                        'tickets'   => ['title' => 'پشتیبانی',     'desc' => 'ارسال تیکت و پیگیری'],
                        'account'   => ['title' => 'جزئیات حساب',  'desc' => 'پروفایل و شخصی‌سازی'],
                    ];
                    foreach ($quickDefaults as $quickKey => $quickDefault) {
                        $allSettings['dashboard']['quick_items'][$quickKey] = [
                            'enabled' => !empty($_POST['dash_quick_enabled'][$quickKey]),
                            'title'   => sanitize_text_field(wp_unslash($_POST['dash_quick_title'][$quickKey] ?? '')) ?: $quickDefault['title'],
                            'desc'    => sanitize_text_field(wp_unslash($_POST['dash_quick_desc'][$quickKey] ?? '')) ?: $quickDefault['desc'],
                        ];
                    }

                    // Orders cards model + counts
                    $allSettings['dashboard']['cards_layout'] = ($_POST['dash_cards_layout'] ?? 'side') === 'stacked' ? 'stacked' : 'side';
                    $allSettings['dashboard']['recent_orders_count']   = max(1, min(10, (int) ($_POST['dash_recent_count'] ?? 4)));
                    $allSettings['dashboard']['frequent_orders_count'] = max(1, min(10, (int) ($_POST['dash_frequent_count'] ?? 3)));

                    // ── Mobile-first layout: fully independent of desktop ──
                    $mobOrder = [];
                    foreach ((array) ($_POST['dash_sections_mobile'] ?? []) as $mobSid) {
                        $mobSid = preg_replace('/[^a-z0-9_]/', '', (string) wp_unslash($mobSid));
                        if (in_array($mobSid, $validDashSections, true) && !in_array($mobSid, $mobOrder, true)) {
                            $mobOrder[] = $mobSid;
                        }
                    }
                    foreach ($validDashSections as $mobSid) {
                        if (!in_array($mobSid, $mobOrder, true)) {
                            $mobOrder[] = $mobSid;
                        }
                    }
                    $mobSections = [];
                    foreach ($mobOrder as $mobSid) {
                        $mobSections[] = [
                            'id'      => $mobSid,
                            'enabled' => !empty($_POST['dash_enabled_mobile'][$mobSid]),
                        ];
                    }

                    $mobMenuModel   = (string) wp_unslash($_POST['dash_mobile_menu_model'] ?? 'toggle');
                    $mobProfileCard = (string) wp_unslash($_POST['dash_mobile_profile_card'] ?? 'full');

                    $allSettings['dashboard']['mobile'] = [
                        'menu_model'      => in_array($mobMenuModel, ['toggle', 'open'], true) ? $mobMenuModel : 'toggle',
                        'profile_card'    => in_array($mobProfileCard, ['full', 'minimal', 'hidden'], true) ? $mobProfileCard : 'full',
                        'sections'        => $mobSections,
                        'stats_columns'   => ((int) ($_POST['dash_mobile_stats_columns'] ?? 2)) === 1 ? 1 : 2,
                        'quick_columns'   => ((int) ($_POST['dash_mobile_quick_columns'] ?? 2)) === 1 ? 1 : 2,
                        'quick_show_desc' => !empty($_POST['dash_mobile_quick_desc']),
                    ];
                    break;
            }

            $this->settings->save($allSettings);
            $saved = true;
        }

        // Render view
        require_once ARMA_PANEL_PATH . 'src/Admin/Views/settings.php';
    }
}
