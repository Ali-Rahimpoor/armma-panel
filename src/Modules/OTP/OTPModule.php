<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\OTP;

use Arma\ArmaPanel\Contracts\ModuleInterface;

class OTPModule implements ModuleInterface {

    public function getId(): string {
        return 'otp';
    }

    public function getTitle(): string {
        return __('رمز یکبار مصرف (OTP)', 'arma-panel');
    }

    public function isRequired(): bool {
        return true; // Mandatory
    }

    public function boot(): void {
        // Core OTP services are consumed via container
    }
}
