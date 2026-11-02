<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class SMSModule implements ModuleInterface {

    public function getId(): string {
        return 'sms';
    }

    public function getTitle(): string {
        return __('سیستم پیامک', 'arma-panel');
    }

    public function isRequired(): bool {
        return true; // Required for OTP dispatch
    }

    public function boot(): void {
        // SMS hooks are handled via SMSManager & WooCommerceManager
    }
}
