<?php
declare(strict_types=1);

namespace Arma\ArmaPanel\Modules\SMS;

/**
 * Result object for SMS operations.
 */
class SMSResult {

    private bool $success;
    private ?string $recId;
    private ?string $errorCode;
    private string $message;

    public function __construct(bool $success, ?string $recId = null, ?string $errorCode = null, string $message = '') {
        $this->success   = $success;
        $this->recId     = $recId;
        $this->errorCode = $errorCode;
        $this->message   = $message;
    }

    public static function success(string $recId, string $message = ''): self {
        return new self(true, $recId, null, $message);
    }

    public static function failure(string $errorCode, string $message): self {
        return new self(false, null, $errorCode, $message);
    }

    public function isSuccessful(): bool {
        return $this->success;
    }

    public function getRecId(): ?string {
        return $this->recId;
    }

    public function getErrorCode(): ?string {
        return $this->errorCode;
    }

    public function getMessage(): string {
        return $this->message;
    }
}
