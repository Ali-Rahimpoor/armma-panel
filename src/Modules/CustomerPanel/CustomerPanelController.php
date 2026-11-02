<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\CustomerPanel;

use Arma\ArmaPanel\Core\SettingsManager;
use Arma\ArmaPanel\Modules\LuckyWheel\LuckyWheelService;
use Arma\ArmaPanel\Modules\Notifications\NotificationService;
use Arma\ArmaPanel\Modules\Tickets\TicketService;
use Arma\ArmaPanel\Modules\Wishlist\WishlistService;
use Arma\ArmaPanel\Modules\WooCommerce\AddressService;
use Arma\ArmaPanel\Modules\WooCommerce\OrderService;
use Arma\ArmaPanel\Support\Phone\PhoneService;
use WP_User;

/**
 * Controller for Customer Panel pages.
 */
class CustomerPanelController {

    private SettingsManager $settings;
    private NavigationManager $navManager;
    private PhoneService $phoneService;
    private OrderService $orderService;
    private AddressService $addressService;
    private ?TicketService $ticketService;
    private ?NotificationService $notificationService;
    private ?LuckyWheelService $luckyWheelService;
    private ?WishlistService $wishlistService;

    public function __construct(
        SettingsManager $settings,
        NavigationManager $navManager,
        PhoneService $phoneService,
        OrderService $orderService,
        AddressService $addressService,
        ?TicketService $ticketService = null,
        ?NotificationService $notificationService = null,
        ?LuckyWheelService $luckyWheelService = null,
        ?WishlistService $wishlistService = null
    ) {
        $this->settings            = $settings;
        $this->navManager          = $navManager;
        $this->phoneService        = $phoneService;
        $this->orderService        = $orderService;
        $this->addressService      = $addressService;
        $this->ticketService       = $ticketService;
        $this->notificationService = $notificationService;
        $this->luckyWheelService   = $luckyWheelService;
        $this->wishlistService     = $wishlistService;
    }

    public function setTicketService(TicketService $service): void {
        $this->ticketService = $service;
    }

    public function setNotificationService(NotificationService $service): void {
        $this->notificationService = $service;
    }

    public function setLuckyWheelService(LuckyWheelService $service): void {
        $this->luckyWheelService = $service;
    }

    public function setWishlistService(WishlistService $service): void {
        $this->wishlistService = $service;
    }

    /**
     * Handle direct (non-AJAX) form submissions before the panel renders,
     * using a safe POST-redirect-GET flow to prevent double submissions.
     */
    public function maybeHandleFormPost(string $section, string $sub = ''): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        // Billing / shipping address forms
        if (isset($_POST['arma_address_form_nonce'])) {
            $this->handleAddressFormPost();
        }

        // Account form (first/last name) is verified and saved by the
        // account template itself and renders inline notices.
    }

    /**
     * Persist a submitted address form and redirect back (PRG pattern).
     */
    private function handleAddressFormPost(): void {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return;
        }

        $nonce = isset($_POST['arma_address_form_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['arma_address_form_nonce']))
            : '';

        if (!wp_verify_nonce($nonce, 'arma_save_address')) {
            return; // Invalid nonce: skip saving, render the page normally.
        }

        $type = (string) ($_POST['address_type'] ?? '') === 'shipping' ? 'shipping' : 'billing';

        $allowedFields = [
            'first_name', 'last_name', 'company', 'address_1', 'address_2',
            'city', 'state', 'postcode', 'country', 'phone',
        ];

        $data = [];
        foreach ($allowedFields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
            }
        }

        $result = $this->addressService->updateAddress(get_current_user_id(), $type, $data);

        $target = $this->addressesUrl();
        $target = add_query_arg($result['success'] ? 'arma_saved' : 'arma_error', $type, $target);

        wp_safe_redirect($target);
        exit;
    }

    /**
     * URL of the addresses section (permalink-structure aware).
     */
    private function addressesUrl(): string {
        $slug = trim((string) $this->settings->get('general.panel_slug', 'arma-panel'), '/');

        if (function_exists('arma_panel_url')) {
            return arma_panel_url('addresses');
        }

        return home_url('/' . $slug . '/addresses/');
    }

    /**
     * Dispatch incoming request to appropriate view.
     *
     * @param string $page
     * @param string $sub
     */
    public function dispatch(string $page, string $sub = ''): void {
        // 1. If user is NOT logged in, show Auth (OTP Login / Register)
        if (!is_user_logged_in()) {
            $this->renderAuthView();
            return;
        }

        $userId = get_current_user_id();
        $user   = wp_get_current_user();

        // 2. Validate section and module enablement
        $page = $this->sanitizeSection($page);

        // 3. Prepare common view data
        $unreadCount = $this->notificationService ? $this->notificationService->getUnreadCount($userId) : 0;
        $phone       = (string) get_user_meta($userId, PhoneService::META_KEY, true);
        if (empty($phone)) {
            $phone = (string) get_user_meta($userId, 'billing_phone', true);
        }

        // Avatar: user-selected bundled avatar (personalization) or default-user.webp
        $avatarUrl = '';
        if (function_exists('arma_avatar_url_for_key')) {
            $avatarUrl = arma_avatar_url_for_key((string) get_user_meta($userId, 'arma_avatar', true));
        }
        if ($avatarUrl === '') {
            $avatarUrl = function_exists('get_avatar_url') ? get_avatar_url($userId, ['size' => 120]) : '';
            if (empty($avatarUrl) || strpos($avatarUrl, 'gravatar.com/avatar') !== false) {
                $avatarUrl = ARMA_PANEL_URL . 'assets/images/default-user.webp';
            }
        }

        $userData = [
            'id'            => $userId,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'display_name'  => trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name,
            'phone'         => $phone,
            'phone_display' => $this->phoneService->format($phone),
            'avatar_url'    => $avatarUrl,
            'unread_count'  => $unreadCount,
        ];

        // 4. Handle section-specific data
        $sectionData = [];

        switch ($page) {
            case 'dashboard':
                $sectionData = $this->getDashboardData($userId);
                break;

            case 'orders':
                $sectionData = $this->getOrdersData($userId, $sub);
                break;

            case 'addresses':
                $sectionData = $this->getAddressesData($userId);
                break;

            case 'account':
                $sectionData = $this->getAccountData($userId);
                break;

            case 'tickets':
                $sectionData = $this->getTicketsData($userId, $sub);
                break;

            case 'notifications':
                $sectionData = $this->getNotificationsData($userId);
                break;

            case 'lucky-wheel':
                $sectionData = $this->getLuckyWheelData($userId);
                break;

            case 'wishlist':
                $sectionData = $this->getWishlistPageData($userId);
                break;
        }

        // 5. Render Layout Template
        $navItems = $this->navManager->getVisibleItems();

        arma_view('panel/layout', [
            'current_page' => $page,
            'user'         => $userData,
            'nav_items'    => $navItems,
            'settings'     => $this->settings,
            'section_data' => $sectionData,
        ]);
    }

    /**
     * Render the OTP Login / Register page for guests.
     */
    private function renderAuthView(): void {
        arma_view('auth/login', [
            'settings' => $this->settings,
        ]);
    }

    /**
     * Sanitize requested section and check module permission.
     */
    private function sanitizeSection(string $page): string {
        $allowed = ['dashboard', 'orders', 'addresses', 'account', 'tickets', 'notifications', 'lucky-wheel', 'wishlist'];

        if (!in_array($page, $allowed, true)) {
            $page = 'dashboard';
        }

        if ($page === 'tickets' && !$this->settings->isModuleEnabled('tickets')) {
            $page = 'dashboard';
        }

        if ($page === 'notifications' && !$this->settings->isModuleEnabled('notifications')) {
            $page = 'dashboard';
        }

        if ($page === 'lucky-wheel' && !$this->settings->isModuleEnabled('lucky_wheel')) {
            $page = 'dashboard';
        }

        if ($page === 'wishlist' && !$this->settings->isModuleEnabled('wishlist')) {
            $page = 'dashboard';
        }

        return $page;
    }

    /**
     * Wishlist page data (module-gated; missing service => empty list).
     *
     * @return array<string, mixed>
     */
    private function getWishlistPageData(int $userId): array {
        $products = [];

        if ($this->wishlistService !== null && $this->wishlistService->isEnabled()) {
            $products = $this->wishlistService->getWishlistData($userId);
        }

        return [
            'products' => $products,
            'count'    => count($products),
        ];
    }

    private function getDashboardData(int $userId): array {
        // v1.6.0: item counts come from the dashboard builder settings
        $recentCount   = max(1, min(10, (int) $this->settings->get('dashboard.recent_orders_count', 4)));
        $frequentCount = max(1, min(10, (int) $this->settings->get('dashboard.frequent_orders_count', 3)));

        $recentOrders   = $this->orderService->getRecentOrders($userId, $recentCount);
        $frequentOrders = array_slice($recentOrders, 0, $frequentCount);

        // Lightweight stats for the overview tiles (ids-only lookups)
        $stats = [
            'orders_total' => 0,
            'orders_active' => 0,
            'tickets_open' => 0,
            'unread'       => $this->notificationService ? (int) $this->notificationService->getUnreadCount($userId) : 0,
        ];

        if ($this->settings->isModuleEnabled('woocommerce')) {
            $stats['orders_total']  = $this->orderService->countCustomerOrders($userId);
            $stats['orders_active'] = $this->orderService->countCustomerOrders($userId, ['processing', 'on-hold']);
        }

        if ($this->settings->isModuleEnabled('tickets') && $this->ticketService) {
            try {
                foreach ($this->ticketService->getTicketRepository()->getForUser($userId, 50) as $ticket) {
                    if (in_array((string) ($ticket->status ?? ''), ['pending', 'open', 'replied'], true)) {
                        $stats['tickets_open']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['tickets_open'] = 0;
            }
        }

        return [
            'recent_orders'   => $recentOrders,
            'frequent_orders' => $frequentOrders,
            'stats'           => $stats,
        ];
    }

    private function getOrdersData(int $userId, string $sub): array {
        if (!empty($sub) && is_numeric($sub)) {
            $singleOrder = $this->orderService->getCustomerOrder((int) $sub, $userId);
            return [
                'is_single' => true,
                'order'     => $singleOrder,
            ];
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $ordersData = $this->orderService->getCustomerOrders($userId, $paged, 10);

        return [
            'is_single'   => false,
            'orders'      => $ordersData['orders'],
            'total'       => $ordersData['total'],
            'total_pages' => $ordersData['total_pages'],
            'current_page'=> $paged,
        ];
    }

    private function getAddressesData(int $userId): array {
        return [
            'addresses' => $this->addressService->getAddresses($userId),
        ];
    }

    private function getAccountData(int $userId): array {
        $user = get_user_by('id', $userId);
        return [
            'user' => $user,
        ];
    }

    private function getTicketsData(int $userId, string $sub): array {
        if (!$this->ticketService) {
            return ['enabled' => false];
        }

        if (!empty($sub) && is_numeric($sub)) {
            $details = $this->ticketService->getTicketDetailsForCustomer((int) $sub, $userId);
            return [
                'is_single' => true,
                'details'   => $details,
            ];
        }

        $tickets = $this->ticketService->getTicketRepository()->getForUser($userId, 20);

        return [
            'is_single' => false,
            'tickets'   => $tickets,
        ];
    }

    private function getNotificationsData(int $userId): array {
        if (!$this->notificationService) {
            return ['enabled' => false];
        }

        $notifications = $this->notificationService->getForUser($userId, 30);

        return [
            'notifications' => $notifications,
        ];
    }

    private function getLuckyWheelData(int $userId): array {
        $orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $token   = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        $canSpin = false;
        if ($this->luckyWheelService && $orderId > 0 && !empty($token)) {
            $canSpin = $this->luckyWheelService->canSpin($orderId, $userId, $token);
        }

        // Thank-you landing page: order summary + the customer dashboard below
        // the wheel, so the user keeps browsing instead of hitting a dead end.
        $orderSummary = null;
        if ($orderId > 0 && $this->orderService) {
            $order = $this->orderService->getCustomerOrder($orderId, $userId);
            if (is_array($order)) {
                $orderSummary = $order;
            }
        }

        $alreadySpun = false;
        if ($orderId > 0 && $this->luckyWheelService) {
            // HPOS-safe read (order meta API + legacy postmeta fallback)
            $alreadySpun = $this->luckyWheelService->readSpunFlag($orderId) === '1';
        }

        return [
            'can_spin'      => $canSpin,
            'order_id'      => $orderId,
            'token'         => $token,
            'order_summary' => $orderSummary,
            'already_spun'  => $alreadySpun,
            'dashboard'     => $this->getDashboardData($userId),
        ];
    }
}
