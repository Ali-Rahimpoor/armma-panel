<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\CustomerPanel;

use Arma\ArmaPanel\Core\SettingsManager;

/**
 * Panel Page Manager.
 *
 * Ensures a real WordPress page exists for the customer panel (like WooCommerce
 * does for /my-account/). Using a real page guarantees:
 *  - Correct browser title (no "برگه پیدا نشد" / 404 titles).
 *  - Correct 200 HTTP status and canonical behavior.
 *  - Native active-theme header/footer integration.
 */
class PanelPageManager {

    public const PAGE_ID_OPTION = 'arma_panel_page_id';

    /** Option holding the unresolved slug-conflict notice. */
    public const SLUG_CONFLICT_OPTION = 'arma_panel_slug_conflict';

    private SettingsManager $settings;

    /** @var int|null cached page id */
    private ?int $pageId = null;

    /** @var bool ensurePage() already completed once in this process */
    private bool $pageEnsured = false;

    public function __construct(SettingsManager $settings) {
        $this->settings = $settings;
    }

    public function registerHooks(): void {
        add_action('init', [$this, 'ensurePage'], 5);
        add_action('admin_notices', [$this, 'renderSlugConflictNotice']);
        add_shortcode('arma_panel', [$this, 'renderShortcode']);
    }

    /**
     * Get (and lazily ensure) the panel page ID.
     */
    public function getPageId(): int {
        if ($this->pageId !== null) {
            return $this->pageId;
        }

        $this->pageId = 0;

        if (!function_exists('get_option')) {
            return 0;
        }

        $storedId = (int) get_option(self::PAGE_ID_OPTION, 0);
        if ($storedId > 0) {
            $post = function_exists('get_post') ? get_post($storedId) : null;
            if ($post && $post->post_type === 'page' && $post->post_status !== 'trash') {
                $this->pageId = $storedId;

                return $storedId;
            }
        }

        // Fallback: locate an existing page by slug before creating a new one.
        if (function_exists('get_page_by_path')) {
            $slug   = $this->getSlug();
            $existing = get_page_by_path($slug);
            if ($existing && $existing->post_status === 'publish') {
                $this->pageId = (int) $existing->ID;
                update_option(self::PAGE_ID_OPTION, $this->pageId, true);

                return $this->pageId;
            }
        }

        return 0;
    }

    /**
     * Create the panel page on demand and keep its slug/title synced with settings.
     *
     * The whole check (get_post / get_page_by_path / possible writes) runs at
     * most ONCE per process: activation calls this directly AND init:5 fires
     * in the same request — the second call must not repeat the queries.
     */
    public function ensurePage(): void {
        if ($this->pageEnsured) {
            return; // already ensured in this process — no repeat queries
        }

        if (!function_exists('wp_insert_post')) {
            return;
        }

        $this->pageEnsured = true;

        $slug  = $this->getSlug();
        $title = $this->getTitle();
        $pageId = $this->getPageId();

        // 1. Page missing -> create it.
        if ($pageId <= 0) {
            $newId = wp_insert_post([
                'post_title'     => $title,
                'post_name'      => $slug,
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'post_content'   => '<!-- wp:shortcode -->[arma_panel]<!-- /wp:shortcode -->',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ]);

            if ($newId > 0 && !is_wp_error($newId)) {
                $this->pageId = (int) $newId;
                update_option(self::PAGE_ID_OPTION, $this->pageId, true);
            }

            return;
        }

        // 2. Page exists -> keep slug and title in sync with the settings.
        $post = get_post($pageId);
        if (!$post) {
            return;
        }

        $needsUpdate = [];
        if ($post->post_name !== $slug) {
            /*
             * only sync the slug when it is actually FREE. When
             * another page (theme/landing page) already holds the desired
             * slug, wp_update_post would either fail or get suffixed — on
             * EVERY request. Instead: flag the conflict once, warn the
             * admin, and stop writing.
             */
            $holder = function_exists('get_page_by_path') ? get_page_by_path($slug) : null;

            if ($holder && (int) $holder->ID !== $pageId) {
                $this->flagSlugConflict($slug, $holder);

                return; // never fight over the slug on every request
            }

            $this->clearSlugConflict();
            $needsUpdate['post_name'] = $slug;
        } else {
            $this->clearSlugConflict();
        }

        if ($post->post_title !== $title && trim((string) $post->post_title) === '') {
            // Only fill empty titles; never fight with a merchant-chosen page title.
            $needsUpdate['post_title'] = $title;
        }

        if (!empty($needsUpdate)) {
            $needsUpdate['ID'] = $pageId;
            wp_update_post($needsUpdate);
        }
    }

    /**
     * Record the slug conflict ONCE so an admin notice can be
     * shown without re-writing the option on every request.
     */
    private function flagSlugConflict(string $slug, object $holder): void {
        if (!function_exists('update_option')) {
            return;
        }

        $existing = (string) get_option(self::SLUG_CONFLICT_OPTION, '');

        if ($existing === '') {
            update_option(
                self::SLUG_CONFLICT_OPTION,
                $slug . '|' . (int) $holder->ID,
                true
            );
        }
    }

    /**
     * The conflict resolved itself (slug freed / setting changed): drop the flag.
     */
    private function clearSlugConflict(): void {
        if (function_exists('delete_option') && get_option(self::SLUG_CONFLICT_OPTION, '') !== '') {
            delete_option(self::SLUG_CONFLICT_OPTION);
        }
    }

    /**
     * Admin warning while the panel page's configured slug is held by
     * another page — the panel URL then falls back to the page's real slug.
     */
    public function renderSlugConflictNotice(): void {
        if (!function_exists('get_option') || !function_exists('current_user_can') || !current_user_can('manage_options')) {
            return;
        }

        $raw = (string) get_option(self::SLUG_CONFLICT_OPTION, '');
        if ($raw === '') {
            return;
        }

        [$slug, $holderId] = array_pad(explode('|', $raw, 2), 2, '');
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Arma Panel:</strong>
                <?php echo esc_html(sprintf(
                    /* translators: 1: desired slug, 2: conflicting page ID */
                    __('نشانک (slug) «%1$s» که برای صفحه پنل تنظیم شده است، در حال حاضر متعلق به برگه دیگری (شناسه %2$s) است؛ نشانک پنل اعمال نشد. لطفاً نشانک را در تنظیمات تغییر دهید یا برگه تداخل‌کننده را ویرایش کنید.', 'arma-panel'),
                    (string) $slug,
                    (string) $holderId
                )); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Full URL to a panel section, permalink-structure aware.
     */
    public function getUrl(string $section = 'dashboard'): string {
        $section = trim($section, '/');
        if ($section === '' || $section === 'dashboard') {
            $section = '';
        }

        $suffix = $section !== '' ? rawurlencode($section) . '/' : '';

        // Pretty permalinks via the real page — but ONLY when the WordPress
        // rewrite object exists. During `plugins_loaded` (and any moment
        // before init) $GLOBALS['wp_rewrite'] is still null and calling
        // get_permalink() fatals with:
        //   "Call to a member function get_page_permastruct() on null"
        $pageId = $this->getPageId();
        if ($pageId > 0 && $this->canUsePermalinks()) {
            try {
                $base = (string) get_permalink($pageId);

                if ($base !== '') {
                    return rtrim($base, '/') . '/' . $suffix;
                }
            } catch (\Throwable $e) {
                // Broken permalink filters etc. — fall through to the safe path.
            }
        }

        // Safe fallback (works at ANY point of the request, even plugins_loaded):
        // home_url() reads the option table only and never touches wp_rewrite.
        return home_url('/' . $this->getSlug() . '/' . $suffix);
    }

    /**
     * Whether pretty-permalink functions are safe to call right now.
     */
    private function canUsePermalinks(): bool {
        if (!function_exists('get_permalink')) {
            return false;
        }

        // The rewrite object is created in wp-settings.php AFTER plugins_loaded.
        if (!isset($GLOBALS['wp_rewrite']) || !is_object($GLOBALS['wp_rewrite'])) {
            return false;
        }

        return true;
    }

    /**
     * Shortcode fallback so the panel still renders inside a theme page template
     * if template dispatch is bypassed by another plugin.
     */
    public function renderShortcode(): string {
        if (!defined('ARMA_PANEL_PATH')) {
            return '';
        }

        ob_start();

        /** @var CustomerPanelController $controller */
        $controller = arma_app('panel_controller');
        $controller->dispatch(self::resolveRequestedSection());

        return (string) ob_get_clean();
    }

    /**
     * Resolve the requested section from the current request (query var / superglobals).
     */
    public static function resolveRequestedSection(): string {
        if (function_exists('get_query_var')) {
            $page = (string) get_query_var('arma_panel_page');
            if ($page !== '') {
                return $page;
            }
        }

        if (isset($_GET['arma_panel_page'])) {
            return preg_replace('/[^a-z0-9\-]/', '', strtolower((string) wp_unslash($_GET['arma_panel_page'])));
        }

        return '';
    }

    private function getSlug(): string {
        return trim((string) $this->settings->get('general.panel_slug', 'arma-panel'), '/');
    }

    private function getTitle(): string {
        $title = (string) $this->settings->get('general.panel_title', 'حساب کاربری من');

        return $title !== '' ? $title : 'حساب کاربری من';
    }
}
