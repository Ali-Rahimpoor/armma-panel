<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Tickets;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class TicketsModule implements ModuleInterface {

    private TicketController $controller;

    public function __construct(TicketController $controller) {
        $this->controller = $controller;
    }

    public function getId(): string {
        return 'tickets';
    }

    public function getTitle(): string {
        return __('تیکتینگ و پشتیبانی', 'arma-panel');
    }

    public function isRequired(): bool {
        return false; // Optional
    }

    public function boot(): void {
        $this->controller->registerHooks();
    }
}
