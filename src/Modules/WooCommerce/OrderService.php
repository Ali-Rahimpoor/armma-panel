<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use WC_Order;
use WC_Order_Item_Product;

/**
 * Order Service interacting cleanly with WooCommerce APIs.
 */
class OrderService {

    /**
     * Get recent orders for a specific user.
     *
     * @param int $userId
     * @param int $limit
     * @return array<array<string, mixed>>
     */
    public function getRecentOrders(int $userId, int $limit = 5): array {
        if ($userId <= 0 || !function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'customer_id' => $userId,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);

        $result = [];
        foreach ($orders as $order) {
            if ($order instanceof WC_Order) {
                $result[] = $this->formatOrderData($order);
            }
        }

        return $result;
    }

    /**
     * Count customer orders (optionally filtered by statuses).
     * Uses ids-only lookup to stay cheap on large stores.
     *
     * @param int $userId
     * @param array<int, string> $statuses
     * @return int
     */
    public function countCustomerOrders(int $userId, array $statuses = []): int {
        if ($userId <= 0 || !function_exists('wc_get_orders')) {
            return 0;
        }

        try {
            // Total (no status filter): use the WooCommerce core COUNT helper
            if ($statuses === [] && function_exists('wc_get_customer_order_count')) {
                return (int) wc_get_customer_order_count($userId);
            }

            $args = [
                'customer_id' => $userId,
                'limit'       => 200,
                'return'      => 'ids',
                'status'      => $statuses === [] ? 'any' : $statuses,
            ];
            return count(wc_get_orders($args));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get paginated customer orders.
     *
     * @param int $userId
     * @param int $page
     * @param int $perPage
     * @return array{orders: array<array<string, mixed>>, total: int, total_pages: int}
     */
    public function getCustomerOrders(int $userId, int $page = 1, int $perPage = 10): array {
        if ($userId <= 0 || !function_exists('wc_get_orders')) {
            return ['orders' => [], 'total' => 0, 'total_pages' => 0];
        }

        $args = [
            'customer_id' => $userId,
            'limit'       => $perPage,
            'page'        => $page,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'paginate'    => true,
        ];

        $results = wc_get_orders($args);

        $formatted = [];
        $ordersList = is_object($results) && isset($results->orders) ? $results->orders : (is_array($results) ? $results : []);
        $totalOrders = is_object($results) && isset($results->total) ? (int) $results->total : count($formatted);

        foreach ($ordersList as $order) {
            if ($order instanceof WC_Order) {
                $formatted[] = $this->formatOrderData($order);
            }
        }

        $totalPages = (int) ceil($totalOrders / max(1, $perPage));

        return [
            'orders'      => $formatted,
            'total'       => $totalOrders,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get single customer order strictly enforcing ownership.
     *
     * @param int $orderId
     * @param int $userId
     * @return array<string, mixed>|null
     */
    public function getCustomerOrder(int $orderId, int $userId): ?array {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return null;
        }

        // Strict ownership check
        if ((int) $order->get_customer_id() !== $userId) {
            return null;
        }

        return $this->formatOrderData($order, true);
    }

    /**
     * Format a WooCommerce order object into panel-ready data.
     *
     * @param WC_Order $order
     * @param bool $detailed
     * @return array<string, mixed>
     */
    public function formatOrderData(WC_Order $order, bool $detailed = false): array {
        $items = [];
        $firstItemTitle = __('محصول حذف شده', 'arma-panel');
        $firstItemImage = '';

        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $product = $item->get_product();
                $title   = $item->get_name();
                $image   = '';

                if ($product) {
                    $imgId = $product->get_image_id();
                    if ($imgId) {
                        $imgUrl = wp_get_attachment_image_url($imgId, 'thumbnail');
                        $image = $imgUrl ?: '';
                    }
                }

                if (empty($firstItemImage) && !empty($image)) {
                    $firstItemImage = $image;
                }

                if ($firstItemTitle === __('محصول حذف شده', 'arma-panel')) {
                    $firstItemTitle = $title;
                }

                if ($detailed) {
                    $items[] = [
                        'name'     => $title,
                        'quantity' => $item->get_quantity(),
                        'total'    => (float) $item->get_total(),
                        'image'    => $image,
                    ];
                }
            }
        }

        $dateCreated = $order->get_date_created();
        $dateStr = $dateCreated ? $dateCreated->date_i18n('Y-m-d H:i:s') : '';

        // Status mapping to Persian
        $statusKey = $order->get_status();
        $statusInfo = $this->mapOrderStatus($statusKey);

        return [
            'id'               => $order->get_id(),
            'order_number'     => $order->get_order_number(),
            'status'           => $statusKey,
            'status_label'     => $statusInfo['label'],
            'tracking_method'  => (string) $order->get_meta('_arma_tracking_method'),
            'tracking_code'    => (string) $order->get_meta('_arma_tracking_code'),
            'status_color'     => $statusInfo['color'],
            'status_bg'        => $statusInfo['bg'],
            'total'            => (float) $order->get_total(),
            'total_formatted'  => function_exists('arma_format_toman') ? arma_format_toman($order->get_total()) : wc_price($order->get_total()),
            'date'             => $dateStr,
            'date_persian'     => function_exists('arma_to_persian_digits') ? arma_to_persian_digits($dateStr) : $dateStr,
            'first_item_title' => $firstItemTitle,
            'first_item_image' => $firstItemImage,
            'item_count'       => $order->get_item_count(),
            'items'            => $items,
            'payment_method'   => $order->get_payment_method_title(),
        ];
    }

    /**
     * Map order status to Persian label and visual colors.
     *
     * @param string $status
     * @return array{label: string, color: string, bg: string}
     */
    public function mapOrderStatus(string $status): array {
        $map = [
            'completed'  => [
                'label' => __('تکمیل شده', 'arma-panel'),
                'color' => '#059669',
                'bg'    => '#ecfdf5',
            ],
            'processing' => [
                'label' => __('در حال پردازش', 'arma-panel'),
                'color' => '#2563eb',
                'bg'    => '#eff6ff',
            ],
            'pending'    => [
                'label' => __('در انتظار پرداخت', 'arma-panel'),
                'color' => '#d97706',
                'bg'    => '#fffbeb',
            ],
            'on-hold'    => [
                'label' => __('در انتظار بررسی', 'arma-panel'),
                'color' => '#d97706',
                'bg'    => '#fffbeb',
            ],
            'cancelled'  => [
                'label' => __('لغو شده', 'arma-panel'),
                'color' => '#e11d48',
                'bg'    => '#fff1f2',
            ],
            'refunded'   => [
                'label' => __('مسترد شده', 'arma-panel'),
                'color' => '#64748b',
                'bg'    => '#f8fafc',
            ],
            'failed'     => [
                'label' => __('ناموفق', 'arma-panel'),
                'color' => '#ef4444',
                'bg'    => '#fef2f2',
            ],
        ];

        return $map[$status] ?? [
            'label' => wc_get_order_status_name($status),
            'color' => '#64748b',
            'bg'    => '#f1f5f9',
        ];
    }
}
