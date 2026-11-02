<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\CustomerPanel;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class CustomerPanelModule implements ModuleInterface {

    private PanelRouter $router;
    private PanelPageManager $pageManager;

    public function __construct(PanelRouter $router, ?PanelPageManager $pageManager = null) {
        $this->router      = $router;
        $this->pageManager = $pageManager ?? $router->getPageManager();
    }

    public function getId(): string {
        return 'customer_panel';
    }

    public function getTitle(): string {
        return __('پنل کاربری اختصاصی', 'arma-panel');
    }

    public function isRequired(): bool {
        return true;
    }

    public function boot(): void {
        // 1. Ensure the real panel page + [arma_panel] shortcode exist.
        $this->pageManager->registerHooks();

        // 2. Router: rewrite rules, titles, dispatching.
        $this->router->registerHooks();
    }
}
