<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Authentication;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class AuthenticationModule implements ModuleInterface {

    private AuthController $controller;

    public function __construct(AuthController $controller) {
        $this->controller = $controller;
    }

    public function getId(): string {
        return 'authentication';
    }

    public function getTitle(): string {
        return __('احراز هویت و ورود پیامکی', 'arma-panel');
    }

    public function isRequired(): bool {
        return true; // Cannot be disabled
    }

    public function boot(): void {
        $this->controller->registerHooks();
    }
}
