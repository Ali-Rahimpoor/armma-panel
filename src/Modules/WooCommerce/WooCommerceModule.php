<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\WooCommerce;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class WooCommerceModule implements ModuleInterface {

    private WooCommerceManager $manager;
    private ?GuestCheckoutManager $guestCheckoutManager;
    private ?ShipmentTrackingManager $shipmentTrackingManager;
    private ?AbandonedCartManager $abandonedCartManager;

    public function __construct(
        WooCommerceManager $manager,
        ?GuestCheckoutManager $guestCheckoutManager = null,
        ?ShipmentTrackingManager $shipmentTrackingManager = null,
        ?AbandonedCartManager $abandonedCartManager = null
    ) {
        $this->manager                 = $manager;
        $this->guestCheckoutManager    = $guestCheckoutManager;
        $this->shipmentTrackingManager = $shipmentTrackingManager;
        $this->abandonedCartManager    = $abandonedCartManager;
    }

    public function getId(): string {
        return 'woocommerce';
    }

    public function getTitle(): string {
        return __('یکپارچه‌سازی ووکامرس', 'arma-panel');
    }

    public function isRequired(): bool {
        return true;
    }

    public function boot(): void {
        $this->manager->registerHooks();

        if ($this->guestCheckoutManager instanceof GuestCheckoutManager) {
            $this->guestCheckoutManager->registerHooks();
        }

        // Order-detail shipment tracking box (admin) — requires the order SMS module.
        if ($this->shipmentTrackingManager instanceof ShipmentTrackingManager
            && $this->shipmentTrackingManager->isFeatureEnabled()
        ) {
            $this->shipmentTrackingManager->registerHooks();
        }

        // Abandoned-cart reminder cron (gated by the same module).
        if ($this->abandonedCartManager instanceof AbandonedCartManager
            && $this->abandonedCartManager->isFeatureEnabled()
        ) {
            $this->abandonedCartManager->registerHooks();
        }
    }
}
