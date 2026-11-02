<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Wishlist;

use Arma\ArmaPanel\Contracts\ModuleInterface;

/**
 * Wishlist (favorites) module — heart button on product pages plus the
 * favorites page inside the customer panel.
 */
class WishlistModule implements ModuleInterface {

    private WishlistController $controller;

    public function __construct(WishlistController $controller) {
        $this->controller = $controller;
    }

    public function getId(): string {
        return 'wishlist';
    }

    public function getTitle(): string {
        return __('لیست علاقه‌مندی', 'arma-panel');
    }

    public function isRequired(): bool {
        return false; // Optional
    }

    public function boot(): void {
        $this->controller->registerHooks();
    }
}
