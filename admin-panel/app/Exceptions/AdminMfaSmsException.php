<?php

namespace App\Exceptions;

use RuntimeException;

class AdminMfaSmsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $reason,
        public readonly ?int $status = null,
        public readonly ?string $providerCode = null,
    ) {
        parent::__construct($message);
    }

    public function context(): array
    {
        return array_filter([
            'provider' => $this->provider,
            'reason' => $this->reason,
            'status' => $this->status,
            'provider_code' => $this->providerCode,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
