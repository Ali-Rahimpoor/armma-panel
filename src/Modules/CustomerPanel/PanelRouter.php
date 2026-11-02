<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\CustomerPanel;

use Arma\ArmaPanel\Core\SettingsManager;

/**
 * Panel Router.
 *
 * Registers rewrite rules bound to the real panel page (WooCommerce-style),
 * fixes the document title, blocks canonical redirects for panel sections and
 * dispatches requests to the CustomerPanelController.
 */
class PanelRouter {

    public const RULES_VERSION   = '3';
    public const REWRITE_HASH_OPTION = 'arma_panel_rewrite_hash';

    private SettingsManager $settings;
    private CustomerPanelController $controller;
    private PanelPageManager $pageManager;

    public function __construct(SettingsManager $settings, CustomerPanelController $controller, ?PanelPageManager $pageManager = null) {
        $this->settings    = $settings;
        $this->controller  = $controller;
        $this->pageManager = $pageManager ?? new PanelPageManager($settings);
    }

    public function registerHooks(): void {
        add_action('init', [$this, 'addRewriteRules'], 10);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 999);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('redirect_canonical', [$this, 'preventCanonicalRedirect'], 10, 2);
        add_filter('pre_get_document_title', [$this, 'filterDocumentTitle'], 20);
        add_filter('document_title_parts', [$this, 'filterDocumentTitleParts'], 20);
        add_filter('wp_title', [$this, 'filterWpTitle'], 20, 2);
        add_action('template_redirect', [$this, 'handleRequest'], 0);
    }

    /* ---------------------------------------------------------------------
     * Rewrite rules
     * ------------------------------------------------------------------- */

    /**
     * Register rewrite rules pointing at the real panel page so WordPress
     * resolves panel URLs as a genuine page (HTTP 200, correct title).
     */
    public function addRewriteRules(): void {
        if (!function_exists('add_rewrite_rule')) {
            return;
        }

        $pageUri = $this->getPageUriForRules();
        $pattern = preg_quote($pageUri, '/');

        add_rewrite_rule(
            '^' . $pattern . '/([^/]+)/([^/]+)/?$',
            'index.php?pagename=' . $pageUri . '&arma_panel_page=$matches[1]&arma_panel_sub=$matches[2]',
            'top'
        );

        add_rewrite_rule(
            '^' . $pattern . '/([^/]+)/?$',
            'index.php?pagename=' . $pageUri . '&arma_panel_page=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^' . $pattern . '/?$',
            'index.php?pagename=' . $pageUri . '&arma_panel_page=dashboard',
            'top'
        );
    }

    /**
     * The real URI of the panel page (e.g. "arma-panel" or "blog/arma-panel"
     * for prefixed permalink structures) so rules work on any structure.
     */
    public function getPageUriForRules(): string {
        $pageId = $this->pageManager->getPageId();

        if ($pageId > 0 && function_exists('get_page_uri')) {
            $uri = trim((string) get_page_uri($pageId), '/');

            if ($uri !== '') {
                return $uri;
            }
        }

        return $this->getSlug();
    }

    /**
     * Self-healing flush: detects stale/missing rewrite rules (e.g. plugin was
     * updated or slug changed without a manual permalink refresh) and flushes
     * them automatically once. This guarantees section URLs never break.
     */
    public function maybeFlushRewriteRules(): void {
        if (!function_exists('flush_rewrite_rules') || !function_exists('get_option')) {
            return;
        }

        $hash = md5(self::RULES_VERSION . '|' . (defined('ARMA_PANEL_VERSION') ? ARMA_PANEL_VERSION : '0') . '|' . $this->getSlug() . '|' . $this->pageManager->getPageId() . '|' . $this->getPageUriForRules());

        if ((string) get_option(self::REWRITE_HASH_OPTION, '') !== $hash) {
            flush_rewrite_rules();
            update_option(self::REWRITE_HASH_OPTION, $hash, true);
        }
    }

    /**
     * Invalidate the stored hash so rules are flushed on the next request
     * (e.g. right after the slug is saved in settings).
     */
    public static function invalidateRewriteHash(): void {
        if (function_exists('delete_option')) {
            delete_option(self::REWRITE_HASH_OPTION);
        }
    }

    /**
     * @param array<string> $vars
     * @return array<string>
     */
    public function registerQueryVars(array $vars): array {
        $vars[] = 'arma_panel_page';
        $vars[] = 'arma_panel_sub';

        return $vars;
    }

    /**
     * Stop WordPress from canonical-redirecting /panel/orders/ back to /panel/.
     */
    public function preventCanonicalRedirect($redirectUrl, $requestedUrl) {
        if ($this->parseSectionFromUri((string) parse_url($requestedUrl, PHP_URL_PATH)) !== null) {
            return false;
        }

        return $redirectUrl;
    }

    /* ---------------------------------------------------------------------
     * Document titles
     * ------------------------------------------------------------------- */

    public function filterDocumentTitle(string $title): string {
        $custom = $this->buildSectionTitle();

        return $custom !== '' ? $custom : $title;
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filterDocumentTitleParts(array $parts): array {
        $custom = $this->buildSectionTitle();

        if ($custom !== '') {
            $parts['title'] = $custom;
            unset($parts['page'], $parts['site']);
        }

        return $parts;
    }

    public function filterWpTitle(string $title, string $sep = '—'): string {
        $custom = $this->buildSectionTitle();

        return $custom !== '' ? $custom . ' ' . $sep . ' ' . get_bloginfo('name') : $title;
    }

    /**
     * Human readable section titles for the browser tab.
     */
    public function getSectionTitles(): array {
        $panelTitle = (string) $this->settings->get('general.panel_title', 'حساب کاربری من');

        return [
            'dashboard'     => $panelTitle !== '' ? $panelTitle : 'حساب کاربری من',
            'orders'        => __('سفارش‌های من', 'arma-panel'),
            'addresses'     => __('نشانی‌های من', 'arma-panel'),
            'account'       => __('جزئیات حساب من', 'arma-panel'),
            'tickets'       => __('پشتیبانی و تیکت‌ها', 'arma-panel'),
            'notifications' => __('اطلاعیه‌های من', 'arma-panel'),
            'lucky-wheel'   => __('گردونه شانس', 'arma-panel'),
            'login'         => __('ورود و ثبت‌نام', 'arma-panel'),
        ];
    }

    private function buildSectionTitle(): string {
        $titles  = $this->getSectionTitles();
        $section = $this->resolveSection();

        return $titles[$section] ?? '';
    }

    /* ---------------------------------------------------------------------
     * Dispatching
     * ------------------------------------------------------------------- */

    /**
     * Handle incoming panel request: resolve the section, force a clean 200
     * response and render the panel inside the active theme.
     */
    public function handleRequest(): void {
        $section = $this->resolveSection(true);

        if ($section === null) {
            return; // Not a panel request; let WordPress continue normally.
        }

        $this->forceOkResponse();

        $sub = '';
        if (function_exists('get_query_var')) {
            $sub = (string) get_query_var('arma_panel_sub');
        }
        if ($sub === '' && isset($_GET['arma_panel_sub'])) {
            $sub = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) wp_unslash($_GET['arma_panel_sub'])));
        }

        // Handle direct (non-AJAX) form submissions before rendering (PRG pattern).
        $this->controller->maybeHandleFormPost($section, $sub);

        nocache_headers();
        $this->controller->dispatch($section, $sub);
        exit;
    }

    /**
     * Resolve the requested panel section or null when this is not a panel URL.
     */
    public function resolveSection(bool $fromGlobals = false): ?string {
        // 1. Query var (pretty permalinks with healthy rewrite rules)
        if (function_exists('get_query_var')) {
            $page = trim((string) get_query_var('arma_panel_page'));
            if ($page !== '') {
                return $this->normalizeSection($page);
            }
        }

        // 2. Plain permalinks / explicit query string (?page_id=X&arma_panel_page=orders)
        if (isset($_GET['arma_panel_page'])) {
            return $this->normalizeSection((string) wp_unslash($_GET['arma_panel_page']));
        }

        // 3. URI fallback (rewrite rules not flushed yet) — parse the path precisely
        if ($fromGlobals) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $section = $this->parseSectionFromUri((string) parse_url($uri, PHP_URL_PATH));

            if ($section !== null) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Pure path parser: given a request path, return [section, sub] when the
     * path targets the panel or null otherwise. Supports subdirectory installs
     * and prefixed permalink structures (slug may appear in first 3 segments).
     *
     * @return array{0: string, 1: string}|null
     */
    public function parsePanelPath(string $path): ?array {
        $slug  = $this->getSlug();
        $parts = array_values(array_filter(explode('/', trim((string) $path, '/')), static fn($p) => $p !== ''));

        if (empty($parts)) {
            return null;
        }

        $slugIndex = array_search($slug, array_slice($parts, 0, 3), true);

        if ($slugIndex === false) {
            return null;
        }

        $section = $parts[$slugIndex + 1] ?? 'dashboard';
        $sub     = $parts[$slugIndex + 2] ?? '';

        return [$this->normalizeSection($section), $sub];
    }

    /**
     * @return string|null section slug or null when path is not a panel URL
     */
    public function parseSectionFromUri(string $path): ?string {
        $parsed = $this->parsePanelPath($path);

        return $parsed ? $parsed[0] : null;
    }

    private function normalizeSection(string $section): string {
        $section = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($section)));

        return $section !== '' ? $section : 'dashboard';
    }

    /**
     * Guarantee an HTTP 200 with proper status even in edge cases where the
     * page lookup failed (e.g. page temporarily trashed) — never render the
     * panel behind a "Page not found" status/title again.
     */
    private function forceOkResponse(): void {
        global $wp_query;

        if (function_exists('status_header')) {
            status_header(200);
        }

        if (is_object($wp_query)) {
            $wp_query->is_404    = false;
            $wp_query->is_page   = true;
            $wp_query->is_single = false;
            $wp_query->is_archive = false;
            $wp_query->is_home   = false;
            $wp_query->queried_object = null;
        }
    }

    /**
     * Public URL helper (delegates to PageManager).
     */
    public function getPanelUrl(string $section = 'dashboard'): string {
        return $this->pageManager->getUrl($section);
    }

    public function getPageManager(): PanelPageManager {
        return $this->pageManager;
    }

    public function getSlug(): string {
        return trim((string) $this->settings->get('general.panel_slug', 'arma-panel'), '/');
    }
}
