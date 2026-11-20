<?php

namespace App\Services\Partners;

use RuntimeException;

/**
 * Error legible de la API de medicamentos no cubiertos. El mensaje no incluye el token ni datos del paciente.
 */
class UncoveredMedicineApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
