<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customer dashboard (main panel page).
 * v1.6.0: block order/visibility, texts and layout models are controlled by
 * the admin from Settings → «چیدمان داشبورد».
 *
 * @var array<string, mixed> $user
 * @var \Arma\ArmaPanel\Core\SettingsManager $settings
 * @var array<string, mixed> $section_data
 */

$recentOrders   = $section_data['recent_orders'] ?? [];
$frequentOrders = $section_data['frequent_orders'] ?? [];
$stats          = $section_data['stats'] ?? ['orders_total' => 0, 'orders_active' => 0, 'tickets_open' => 0, 'unread' => 0];

$ordersUrl = function_exists('arma_panel_url') ? arma_panel_url('orders') : home_url('/' . trim((string)$settings->get('general.panel_slug', 'arma-panel'), '/') . '/orders/');
$shopUrl   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');

$toPersian = static fn($v) => function_exists('arma_to_persian_digits') ? arma_to_persian_digits((string) $v) : (string) $v;
$firstName = trim((string) ($user['first_name'] ?? ''));
$greeting  = $firstName !== '' ? $firstName : (string) ($user['display_name'] ?? '');
$fallbackName = $greeting !== '' ? $greeting : 'کاربر گرامی';

// Decorative sparkline paths (purely visual, aria-hidden)
$sparks = [
    'M2 14 L7 10 L12 12 L17 6 L22 8 L27 3',
    'M2 10 L7 12 L12 7 L17 9 L22 5 L27 6',
    'M2 6 L7 9 L12 5 L17 8 L22 4 L27 7',
    'M2 12 L7 13 L12 9 L17 11 L22 7 L27 9',
];

// ─────────────── Dashboard builder settings (v1.6.0) ───────────────

// v1.7.0 mobile-first: phones have their OWN block list + layout numbers;
// desktop settings are never forced onto the phone view.
$isMobile = function_exists('arma_is_mobile') && arma_is_mobile();

// Block order + visibility (admin-defined; unknown ids appended safely)
$dashSectionsSrc = $isMobile
    ? (array) $settings->get('dashboard.mobile.sections', [])
    : (array) $settings->get('dashboard.sections', []);
$dashSections = [];
foreach ($dashSectionsSrc as $dashSec) {
    if (is_array($dashSec) && (string) ($dashSec['id'] ?? '') !== '') {
        $dashSections[(string) $dashSec['id']] = !empty($dashSec['enabled']);
    }
}
$dashKnown = ['welcome' => true, 'stats' => true, 'quick' => true, 'recent_orders' => true, 'frequent_orders' => true];
foreach ($dashKnown as $dashId => $dashOn) {
    if (!array_key_exists($dashId, $dashSections)) {
        $dashSections[$dashId] = $dashOn;
    }
}

// Welcome card texts ({name} placeholder supported)
$welcomeTitleRaw = (string) $settings->get('dashboard.welcome_title', '');
$welcomeTitle    = $welcomeTitleRaw !== ''
    ? str_replace('{name}', $fallbackName, $welcomeTitleRaw)
    : $fallbackName . ' عزیز، خوش آمدید 🌸';
$welcomeSubtitle = (string) $settings->get('dashboard.welcome_subtitle', '');
if ($welcomeSubtitle === '') {
    $welcomeSubtitle = 'از اینجا می‌توانید سفارش‌ها، نشانی‌ها و سایر جزئیات حساب خود را مدیریت کنید.';
}
$welcomeCtaText = (string) $settings->get('dashboard.welcome_cta_text', '');
if ($welcomeCtaText === '') {
    $welcomeCtaText = 'مشاهده فروشگاه';
}
$welcomeChips = (bool) $settings->get('dashboard.welcome_chips', true);

// Stats tiles: which tiles are shown + how many columns
// (v1.7.0: 1|2 columns on phones, 2..4 on desktop — each controlled separately)
$statsColumns = $isMobile
    ? (int) $settings->get('dashboard.mobile.stats_columns', 2)
    : (int) $settings->get('dashboard.stats_columns', 4);
if ($isMobile) {
    if ($statsColumns < 1 || $statsColumns > 2) {
        $statsColumns = 2;
    }
} elseif ($statsColumns < 2 || $statsColumns > 4) {
    $statsColumns = 4;
}
$statsColsClass = $isMobile ? 'arma-stats--mc' . (int) $statsColumns : 'arma-stats--c' . (int) $statsColumns;
$statsShow  = (array) $settings->get('dashboard.stats_show', ['orders_total', 'orders_active', 'tickets_open', 'unread']);
$statDefs   = [
    'orders_total'  => ['value' => $stats['orders_total'],  'label' => 'کل سفارش‌ها',  'spark' => $sparks[0]],
    'orders_active' => ['value' => $stats['orders_active'], 'label' => 'در حال پردازش', 'spark' => $sparks[1]],
    'tickets_open'  => ['value' => $stats['tickets_open'],  'label' => 'تیکت‌های باز',  'spark' => $sparks[2]],
    'unread'        => ['value' => $stats['unread'],        'label' => 'خوانده‌نشده',   'spark' => $sparks[3]],
];
$statCards = [];
foreach ($statDefs as $statKey => $statDef) {
    if (in_array($statKey, $statsShow, true)) {
        $statCards[$statKey] = $statDef;
    }
}

// Quick actions: per-item visibility + editable title/desc
// (v1.7.0: phones get their own column count + optional descriptions)
$quickColumns = (int) $settings->get('dashboard.mobile.quick_columns', 2);
if ($quickColumns < 1 || $quickColumns > 2) {
    $quickColumns = 2;
}
$quickColsClass = $isMobile ? ' arma-quick--mc' . (int) $quickColumns : '';
if ($isMobile && (bool) $settings->get('dashboard.mobile.quick_show_desc', false)) {
    $quickColsClass .= ' arma-quick--show-desc';
}
$quickDefs = [
    'orders'    => ['url' => $ordersUrl, 'default_title' => 'سفارش‌های من',  'default_desc' => 'پیگیری و مشاهده جزئیات',   'icon' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path>'],
    'addresses' => ['url' => function_exists('arma_panel_url') ? arma_panel_url('addresses') : $ordersUrl, 'default_title' => 'نشانی‌های من', 'default_desc' => 'مدیریت آدرس‌های ارسال', 'icon' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>'],
    'tickets'   => ['url' => function_exists('arma_panel_url') ? arma_panel_url('tickets') : $ordersUrl, 'default_title' => 'پشتیبانی', 'default_desc' => 'ارسال تیکت و پیگیری', 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><line x1="8" y1="9" x2="16" y2="9"></line><line x1="8" y1="13" x2="13" y2="13"></line>'],
    'account'   => ['url' => function_exists('arma_panel_url') ? arma_panel_url('account') : $ordersUrl, 'default_title' => 'جزئیات حساب', 'default_desc' => 'پروفایل و شخصی‌سازی', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>'],
];
$quickItems = [];
foreach ($quickDefs as $quickKey => $quickDef) {
    $quickConf = (array) $settings->get('dashboard.quick_items.' . $quickKey, []);
    if (empty($quickConf['enabled'])) {
        continue;
    }
    $quickItems[$quickKey] = [
        'url'   => $quickDef['url'],
        'title' => trim((string) ($quickConf['title'] ?? '')) !== '' ? (string) $quickConf['title'] : $quickDef['default_title'],
        'desc'  => trim((string) ($quickConf['desc'] ?? '')) !== '' ? (string) $quickConf['desc'] : $quickDef['default_desc'],
        'icon'  => $quickDef['icon'],
    ];
}

// Orders cards: titles + layout model (side-by-side grid or full-width stacked)
$cardsLayout = (string) $settings->get('dashboard.cards_layout', 'side');
$recentTitle   = (string) $settings->get('dashboard.recent_orders_title', '');
if ($recentTitle === '') { $recentTitle = 'آخرین سفارش‌های من'; }
$frequentTitle = (string) $settings->get('dashboard.frequent_orders_title', '');
if ($frequentTitle === '') { $frequentTitle = 'پر تکرار ترین سفارش‌های من'; }

/** Render one orders card (shared markup for both cards). */
$renderOrdersCard = function (array $orders, string $title, string $ordersBaseUrl, string $shopCardUrl, bool $showViewAll) use ($toPersian): void {
    ?>
    <div class="arma-card arma-card--hover">
        <div class="arma-card-header">
            <h2 class="arma-card-header__title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php if ($showViewAll): ?>
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    <?php else: ?>
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    <?php endif; ?>
                </svg>
                <span><?php echo esc_html($title); ?></span>
            </h2>
            <?php if ($showViewAll && !empty($orders)): ?>
                <a href="<?php echo esc_url($ordersBaseUrl); ?>" class="arma-btn arma-btn--outline arma-btn--sm">
                    <?php esc_html_e('مشاهده همه', 'arma-panel'); ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="arma-order-list">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <a href="<?php echo esc_url($ordersBaseUrl . $order['id'] . '/'); ?>" class="arma-order-item">
                        <div class="arma-order-item__right">
                            <div class="arma-order-item__thumb">
                                <?php if (!empty($order['first_item_image'])): ?>
                                    <img src="<?php echo esc_url($order['first_item_image']); ?>" alt="<?php echo esc_attr($order['first_item_title']); ?>">
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="arma-order-item__info">
                                <span class="arma-order-item__title"><?php echo esc_html($order['first_item_title']); ?></span>
                                <span class="arma-order-item__date"><?php echo esc_html($order['date_persian']); ?></span>
                            </div>
                        </div>

                        <div class="arma-order-item__left">
                            <span class="arma-order-item__price"><?php echo esc_html($order['total_formatted']); ?></span>
                            <span class="arma-status-pill" style="color: <?php echo esc_attr($order['status_color']); ?>; background-color: <?php echo esc_attr($order['status_bg']); ?>;">
                                <?php echo esc_html($order['status_label']); ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php if ($showViewAll): ?>
                    <!-- Simple empty state (design system) -->
                    <div class="arma-empty-state">
                        <p class="arma-empty-state__text"><?php esc_html_e('هنوز سفارشی ثبت نکرده‌اید.', 'arma-panel'); ?></p>
                        <a href="<?php echo esc_url($shopCardUrl); ?>" class="arma-btn arma-btn--primary arma-btn--sm">
                            <?php esc_html_e('مشاهده فروشگاه و خرید', 'arma-panel'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="arma-empty-state">
                        <p class="arma-empty-state__text"><?php esc_html_e('موردی یافت نشد.', 'arma-panel'); ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

// ─────────────── Render blocks in the admin-defined order ───────────────
$dashRenderedCards = [];
foreach (array_keys($dashSections) as $dashId) {
    if (empty($dashSections[$dashId])) {
        continue;
    }

    /* ───────────── Welcome card (warm surface, per design system) ───────────── */
    if ($dashId === 'welcome'):
        ?>
        <section class="arma-welcome">
            <div class="arma-welcome__hello">
                <h2 class="arma-welcome__title"><?php echo esc_html($welcomeTitle); ?></h2>
                <p class="arma-welcome__sub"><?php echo esc_html($welcomeSubtitle); ?></p>
            </div>

            <?php if ($welcomeChips): ?>
                <div class="arma-welcome__statuses">
                    <span class="arma-welcome__stat">
                        <span class="arma-dot arma-dot--primary" aria-hidden="true"></span>
                        <strong><?php echo esc_html($toPersian($stats['orders_total'])); ?></strong>
                        <em><?php esc_html_e('سفارش', 'arma-panel'); ?></em>
                    </span>
                    <span class="arma-welcome__sep" aria-hidden="true">·</span>
                    <span class="arma-welcome__stat">
                        <span class="arma-dot arma-dot--warning" aria-hidden="true"></span>
                        <strong><?php echo esc_html($toPersian($stats['orders_active'])); ?></strong>
                        <em><?php esc_html_e('در انتظار بررسی', 'arma-panel'); ?></em>
                    </span>
                    <span class="arma-welcome__sep" aria-hidden="true">·</span>
                    <span class="arma-welcome__stat">
                        <span class="arma-dot arma-dot--info" aria-hidden="true"></span>
                        <strong><?php echo esc_html($toPersian($stats['tickets_open'])); ?></strong>
                        <em><?php esc_html_e('تیکت باز', 'arma-panel'); ?></em>
                    </span>
                </div>
            <?php endif; ?>

            <a href="<?php echo esc_url($shopUrl); ?>" class="arma-welcome__cta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                <span><?php echo esc_html($welcomeCtaText); ?></span>
            </a>
        </section>
        <?php
    /* ───────────── Stat cards (number + label + decorative sparkline) ───────────── */
    elseif ($dashId === 'stats' && !empty($statCards)):
        ?>
        <section class="arma-stats <?php echo esc_attr($statsColsClass); ?>">
            <?php foreach ($statCards as $statCard): ?>
                <div class="arma-stat">
                    <div class="arma-stat__head">
                        <span class="arma-stat__value"><?php echo esc_html($toPersian($statCard['value'])); ?></span>
                        <span class="arma-stat__label"><?php echo esc_html($statCard['label']); ?></span>
                    </div>
                    <svg class="arma-spark" viewBox="0 0 28 16" preserveAspectRatio="none" aria-hidden="true"><polyline points="<?php echo esc_attr($statCard['spark']); ?>"></polyline></svg>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    /* ───────────── Quick actions ───────────── */
    elseif ($dashId === 'quick' && !empty($quickItems)):
        ?>
        <section class="arma-quick<?php echo esc_attr($quickColsClass); ?>">
            <?php foreach ($quickItems as $quickItem): ?>
                <a href="<?php echo esc_url($quickItem['url']); ?>" class="arma-quick__item">
                    <span class="arma-quick__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $quickItem['icon']; // static inline SVG paths ?></svg></span>
                    <span class="arma-quick__title"><?php echo esc_html($quickItem['title']); ?></span>
                    <span class="arma-quick__desc"><?php echo esc_html($quickItem['desc']); ?></span>
                </a>
            <?php endforeach; ?>
        </section>
        <?php
    /* ───────────── Orders cards (grid pair or stacked singles) ───────────── */
    elseif ($dashId === 'recent_orders' && empty($dashRenderedCards['recent_orders'])):
        $dashRenderedCards['recent_orders'] = true;
        $dashPairWithFrequent = ($cardsLayout !== 'stacked')
            && !empty($dashSections['frequent_orders'])
            && empty($dashRenderedCards['frequent_orders']);
        if ($dashPairWithFrequent) {
            $dashRenderedCards['frequent_orders'] = true;
            echo '<div class="arma-orders-grid">';
            $renderOrdersCard($recentOrders, $recentTitle, $ordersUrl, $shopUrl, true);
            $renderOrdersCard($frequentOrders, $frequentTitle, $ordersUrl, $shopUrl, false);
            echo '</div>';
        } else {
            echo '<div class="arma-orders-grid arma-orders-grid--stacked">';
            $renderOrdersCard($recentOrders, $recentTitle, $ordersUrl, $shopUrl, true);
            echo '</div>';
        }
    elseif ($dashId === 'frequent_orders' && empty($dashRenderedCards['frequent_orders'])):
        $dashRenderedCards['frequent_orders'] = true;
        echo '<div class="arma-orders-grid arma-orders-grid--stacked">';
        $renderOrdersCard($frequentOrders, $frequentTitle, $ordersUrl, $shopUrl, false);
        echo '</div>';
    endif;
}
