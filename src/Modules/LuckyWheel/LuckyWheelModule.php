<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\LuckyWheel;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class LuckyWheelModule implements ModuleInterface {

    private LuckyWheelController $controller;

    public function __construct(LuckyWheelController $controller) {
        $this->controller = $controller;
    }

    public function getId(): string {
        return 'lucky_wheel';
    }

    public function getTitle(): string {
        return __('گردونه شانس مشتریان', 'arma-panel');
    }

    public function isRequired(): bool {
        return false; // Optional
    }

    public function boot(): void {
        $this->controller->registerHooks();
    }
}
