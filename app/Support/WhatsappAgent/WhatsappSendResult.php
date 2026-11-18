<?php

namespace App\Support\WhatsappAgent;

final readonly class WhatsappSendResult
{
    public function __construct(
        public bool $sent,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function failed(string $error): self
    {
        return new self(false, null, $error);
    }
}
