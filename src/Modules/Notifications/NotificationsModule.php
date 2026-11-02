<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\Notifications;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class NotificationsModule implements ModuleInterface {

    private NotificationController $controller;

    public function __construct(NotificationController $controller) {
        $this->controller = $controller;
    }

    public function getId(): string {
        return 'notifications';
    }

    public function getTitle(): string {
        return __('سیستم اطلاعیه‌ها', 'arma-panel');
    }

    public function isRequired(): bool {
        return false; // Optional
    }

    public function boot(): void {
        $this->controller->registerHooks();
    }
}
