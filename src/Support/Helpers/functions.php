<?php
declare(strict_types=1);

use Arma\ArmaPanel\Core\Application;
use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Support\Phone\PhoneService;

if (!function_exists('arma_app')) {
    /**
     * Get the main Application instance or a service from the container.
     *
     * @param string|null $service
     * @return mixed
     */
    function arma_app(?string $service = null) {
        $app = Application::getInstance();
        if ($service === null) {
            return $app;
        }
        return $app->getContainer()->get($service);
    }
}

if (!function_exists('arma_settings')) {
    /**
     * Get SettingsManager instance or a specific setting value.
     *
     * @param string|null $key Dot notation key (e.g. 'appearance.primary_color')
     * @param mixed $default
     * @return mixed
     */
    function arma_settings(?string $key = null, $default = null) {
        /** @var SettingsManager $manager */
        $manager = arma_app('settings');
        if ($key === null) {
            return $manager;
        }
        return $manager->get($key, $default);
    }
}

if (!function_exists('arma_phone')) {
    /**
     * Get PhoneService instance.
     *
     * @return PhoneService
     */
    function arma_phone(): PhoneService {
        return arma_app('phone');
    }
}

if (!function_exists('arma_view')) {
    /**
     * Render a template file with variables.
     *
     * @param string $template Path relative to templates/
     * @param array<string, mixed> $data
     * @param bool $echo
     * @return string
     */
    function arma_view(string $template, array $data = [], bool $echo = true): string {
        $file = ARMA_PANEL_PATH . 'templates/' . ltrim($template, '/') . '.php';

        if (!file_exists($file)) {
            $output = sprintf('<!-- Template not found: %s -->', esc_html($template));
            if ($echo) {
                echo $output;
            }
            return $output;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        $output = (string) ob_get_clean();

        if ($echo) {
            echo $output;
        }
        return $output;
    }
}

if (!function_exists('arma_format_toman')) {
    /**
     * Format a price number into Persian/Toman display.
     *
     * @param float|int|string $amount
     * @return string
     */
    function arma_format_toman($amount): string {
        $num = (float) $amount;
        $formatted = number_format($num, 0, '.', ',');
        // Convert to Persian digits
        $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian_formatted = str_replace($english_digits, $persian_digits, $formatted);
        return $persian_formatted . ' تومان';
    }
}

if (!function_exists('arma_to_persian_digits')) {
    /**
     * Convert English digits to Persian digits.
     *
     * @param string|int $input
     * @return string
     */
    function arma_to_persian_digits($input): string {
        $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($english_digits, $persian_digits, (string) $input);
    }
}

if (!function_exists('arma_is_mobile')) {
    /**
     * Mobile detection for layout decisions (v1.7.0 mobile-first layout).
     *
     * Uses WordPress' own wp_is_mobile() when available; the explicit
     * ?arma_mobile_preview=1 query flag forces the phone layout so admins
     * can preview their mobile configuration from a desktop browser.
     */
    function arma_is_mobile(): bool {
        if (isset($_GET['arma_mobile_preview'])) {
            return true;
        }

        return function_exists('wp_is_mobile') ? (bool) wp_is_mobile() : false;
    }
}

if (!function_exists('arma_panel_url')) {
    /**
     * Build a customer panel URL for a section, respecting the site permalink
     * structure and the custom panel slug.
     *
     * @param string $section e.g. 'dashboard', 'orders', 'lucky-wheel'
     * @return string
     */
    function arma_panel_url(string $section = 'dashboard'): string {
        $app = Application::getInstance();
        $container = $app->getContainer();

        if ($container->has('panel_page_manager')) {
            /** @var \Arma\ArmaPanel\Modules\CustomerPanel\PanelPageManager $manager */
            $manager = $container->get('panel_page_manager');

            return $manager->getUrl($section);
        }

        // Minimal fallback (non-container contexts)
        $slug = 'arma-panel';
        if (class_exists(SettingsManager::class)) {
            $slug = trim((string) Application::getInstance()->getContainer()->get('settings')->get('general.panel_slug', 'arma-panel'), '/');
        }

        $section = trim($section, '/');
        $path    = '/' . $slug . '/' . ($section !== '' && $section !== 'dashboard' ? $section . '/' : '');

        return function_exists('home_url') ? home_url($path) : $path;
    }
}

if (!function_exists('arma_safe_redirect_target')) {
    /**
     * Validate a user-supplied redirect target: only same-site URLs (or
     * site-relative paths) are allowed. Prevents open-redirect attacks via
     * the ?redirect_to= parameter in the OTP login flow.
     *
     * @param string $target
     * @param string $fallback
     * @return string
     */
    function arma_safe_redirect_target(string $target, string $fallback): string {
        $target = trim(str_replace(['"', "'", '<', '>', "\0", "\n", "\r"], '', $target));

        if ($target === '') {
            return $fallback;
        }

        // Relative paths inside the site are always safe.
        if ($target[0] === '/' && $target[1] !== '/' && strpos($target, '\\') === false) {
            return $target;
        }

        if (function_exists('wp_validate_redirect')) {
            $validated = wp_validate_redirect($target, $fallback);

            return $validated !== false ? (string) $validated : $fallback;
        }

        // No WP validator available: allow only the current host.
        $host = parse_url($target, PHP_URL_HOST);
        $site = function_exists('home_url') ? parse_url(home_url('/'), PHP_URL_HOST) : null;

        return ($host && $site && strcasecmp($host, (string) $site) === 0) ? $target : $fallback;
    }
}

if (!function_exists('arma_avatar_choices')) {
    /**
     * Whitelisted account avatar choices for customer personalization.
     * 'default' = bundled default-user.webp; the rest are local SVG files
     * shipped with the plugin (no external URLs are ever accepted).
     *
     * @return array<string, string> key => URL
     */
    function arma_avatar_choices(): array {
        $keys = ['default', 'avatar-rose', 'avatar-amber', 'avatar-emerald', 'avatar-sky', 'avatar-violet', 'avatar-slate'];

        $choices = [];
        foreach ($keys as $key) {
            $choices[$key] = $key === 'default'
                ? ARMA_PANEL_URL . 'assets/images/default-user.webp'
                : ARMA_PANEL_URL . 'assets/images/avatars/' . $key . '.svg';
        }

        return $choices;
    }
}

if (!function_exists('arma_avatar_url_for_key')) {
    /**
     * Resolve a stored avatar key to its URL (whitelist-only, '' when unknown).
     */
    function arma_avatar_url_for_key(string $key): string {
        if ($key === '') {
            return '';
        }
        $choices = arma_avatar_choices();
        return $choices[$key] ?? '';
    }
}

if (!function_exists('arma_login_font_assets')) {
    /**
     * Font package dedicated to the OTP login/registration page.
     *
     * When "appearance.login_font_enabled" is on, the LOCALLY-BUNDLED Vazirmatn
     * variable font (single optimized woff2, plugin directory — no external
     * requests) is force-applied to the whole login page including form
     * controls. When off, nothing extra is loaded and the page falls back to
     * the dashboard font choice (arma_font_assets()).
     *
     * @return array{enabled: bool, faces: string, family: string}
     */
    function arma_login_font_assets(): array {
        try {
            $settings = \Arma\ArmaPanel\Core\Application::getInstance()->getContainer()->get('settings');
            $enabled  = (bool) $settings->get('appearance.login_font_enabled', true);
        } catch (\Throwable $e) {
            $enabled = false;
        }

        if ($enabled) {
            $url = esc_url(ARMA_PANEL_URL . 'assets/fonts/Vazirmatn.woff2');
            return [
                'enabled' => true,
                'faces'   => "@font-face{font-family:'Vazirmatn';src:url('{$url}') format('woff2-variations'),url('{$url}') format('woff2');font-weight:100 900;font-style:normal;font-display:swap;}",
                'family'  => "'Vazirmatn', Tahoma, -apple-system, sans-serif",
            ];
        }

        $fallback = function_exists('arma_font_assets') ? arma_font_assets() : ['faces' => '', 'family' => 'inherit'];

        return [
            'enabled' => false,
            'faces'   => (string) $fallback['faces'],
            'family'  => $fallback['family'] !== '' ? $fallback['family'] : 'inherit',
        ];
    }
}

if (!function_exists('arma_font_assets')) {
    /**
     * Build the @font-face rules and CSS font-family stack for the selected
     * dashboard font. Shared by the customer panel layout AND the OTP login
     * page so both use the same font.
     *
     * @return array{faces: string, family: string}
     */
    function arma_font_assets(): array {
        /** @var \Arma\ArmaPanel\Core\SettingsManager $settings */
        try {
            $settings = \Arma\ArmaPanel\Core\Application::getInstance()->getContainer()->get('settings');
            $choice     = (string) $settings->get('appearance.font_family', 'inherit');
            $customName = (string) $settings->get('appearance.custom_font_name', '');
            $customUrl  = (string) $settings->get('appearance.custom_font_url', '');
        } catch (\Throwable $e) {
            return ['faces' => '', 'family' => 'inherit'];
        }

        $faces  = '';
        $family = 'inherit';

        if ($choice === 'vazirmatn') {
            $url = esc_url(ARMA_PANEL_URL . 'assets/fonts/Vazirmatn.woff2');
            $faces  = "@font-face{font-family:'Vazirmatn';src:url('{$url}') format('woff2-variations'),url('{$url}') format('woff2');font-weight:100 900;font-style:normal;font-display:swap;}";
            $family = "'Vazirmatn', Tahoma, -apple-system, sans-serif";
        } elseif ($choice === 'shabnam') {
            $u1 = esc_url(ARMA_PANEL_URL . 'assets/fonts/Shabnam.woff2');
            $u2 = esc_url(ARMA_PANEL_URL . 'assets/fonts/Shabnam-Bold.woff2');
            $faces  = "@font-face{font-family:'Shabnam';src:url('{$u1}') format('woff2');font-weight:400;font-style:normal;font-display:swap;}"
                . "@font-face{font-family:'Shabnam';src:url('{$u2}') format('woff2');font-weight:700;font-style:normal;font-display:swap;}";
            $family = "'Shabnam', Tahoma, -apple-system, sans-serif";
        } elseif ($choice === 'iranyekan') {
            $family = "'IRANYekan', 'IRANYekanX', 'IRANSans', Tahoma, sans-serif";
        } elseif ($choice === 'yekanbakh') {
            $family = "'YekanBakh', 'Yekan Bakh', 'Yekan', Tahoma, sans-serif";
        } elseif ($choice === 'custom' && $customName !== '') {
            $family = "'" . esc_attr($customName) . "', Tahoma, sans-serif";

            if ($customUrl !== '') {
                $ext    = strtolower((string) pathinfo((string) parse_url($customUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                $format = match ($ext) {
                    'woff2' => 'woff2',
                    'woff'  => 'woff',
                    'ttf'   => 'truetype',
                    'otf'   => 'opentype',
                    default => 'woff2',
                };
                $faces = "@font-face{font-family:'" . esc_attr($customName) . "';src:url('" . esc_url($customUrl) . "') format('{$format}');font-weight:100 900;font-style:normal;font-display:swap;}";
            }
        }

        return ['faces' => $faces, 'family' => $family];
    }
}
