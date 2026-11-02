<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\CustomerPanel;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\Notifications\NotificationService;

/**
 * Navigation Manager for building dynamic sidebar menu items.
 *
 * Items are registered with LAZY route resolvers: no permalink/URL function
 * is called from the constructor, because this class is instantiated during
 * `plugins_loaded` — before WordPress creates the rewrite object. Calling
 * get_permalink()/get_permalink()-based helpers at that point causes a fatal
 * error ("Call to a member function get_page_permastruct() on null").
 */
class NavigationManager {

    private SettingsManager $settings;
    private ?NotificationService $notificationService;

    /**
     * @var array<string, NavigationItem>
     */
    private array $items = [];

    public function __construct(SettingsManager $settings, ?NotificationService $notificationService = null) {
        $this->settings            = $settings;
        $this->notificationService = $notificationService;
        $this->registerDefaultItems();
    }

    public function setNotificationService(NotificationService $service): void {
        $this->notificationService = $service;
    }

    /**
     * Build a lazy, permalink-aware route resolver for a panel section.
     *
     * @param string $section
     * @return \Closure(): string
     */
    private function lazyRoute(string $section): \Closure {
        $settings = $this->settings;

        return static function () use ($section, $settings): string {
            if (function_exists('arma_panel_url')) {
                $url = arma_panel_url($section);

                if ($url !== '') {
                    return $url;
                }
            }

            // Safe fallback: home_url() never touches the rewrite object.
            $slug = trim((string) $settings->get('general.panel_slug', 'arma-panel'), '/');

            return function_exists('home_url')
                ? home_url('/' . $slug . '/' . ($section !== 'dashboard' ? $section . '/' : ''))
                : '/' . $slug . '/' . ($section !== 'dashboard' ? $section . '/' : '');
        };
    }

    /**
     * Register default panel navigation items.
     */
    private function registerDefaultItems(): void {
        // 1. Dashboard (پیشخوان)
        $this->addItem(new NavigationItem(
            'dashboard',
            __('پیشخوان', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
            $this->lazyRoute('dashboard'),
            null,
            10
        ));

        // 2. Orders (سفارش‌ها)
        $this->addItem(new NavigationItem(
            'orders',
            __('سفارش‌ها', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>',
            $this->lazyRoute('orders'),
            'woocommerce',
            20
        ));

        // 2b. Wishlist (علاقه‌مندی‌ها) — heart module
        $this->addItem(new NavigationItem(
            'wishlist',
            __('علاقه‌مندی‌ها', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
            $this->lazyRoute('wishlist'),
            'wishlist',
            25
        ));

        // 3. Tickets (پشتیبانی و تیکت‌ها)
        $this->addItem(new NavigationItem(
            'tickets',
            __('پشتیبانی و تیکت‌ها', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
            $this->lazyRoute('tickets'),
            'tickets',
            30
        ));

        // 4. Addresses (نشانی)
        $this->addItem(new NavigationItem(
            'addresses',
            __('نشانی', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
            $this->lazyRoute('addresses'),
            'woocommerce',
            40
        ));

        // 5. Account (جزئیات حساب)
        $this->addItem(new NavigationItem(
            'account',
            __('جزئیات حساب', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            $this->lazyRoute('account'),
            null,
            50
        ));

        // 6. Notifications (اطلاعیه‌ها)
        $this->addItem(new NavigationItem(
            'notifications',
            __('اطلاعیه‌ها', 'arma-panel'),
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
            $this->lazyRoute('notifications'),
            'notifications',
            60
        ));
    }

    public function addItem(NavigationItem $item): void {
        $this->items[$item->getId()] = $item;
    }

    /**
     * Get filtered and sorted navigation items for the current view.
     *
     * @return array<NavigationItem>
     */
    public function getVisibleItems(): array {
        $filtered = [];

        foreach ($this->items as $item) {
            $module = $item->getModule();

            // If item belongs to an optional module and it's disabled, skip
            if ($module !== null && !$this->settings->isModuleEnabled($module)) {
                continue;
            }

            $filtered[] = $item;
        }

        // Sort by position
        usort($filtered, function (NavigationItem $a, NavigationItem $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $filtered;
    }
}
