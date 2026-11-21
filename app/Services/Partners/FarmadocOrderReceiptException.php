<?php

namespace App\Services\Partners;

use RuntimeException;

/**
 * Error de la consulta de pedidos que Farmadoc envió a Tu Dr. Group. El mensaje no incluye el token.
 */
class FarmadocOrderReceiptException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $error = null,
    ) {
        parent::__construct($message);
    }

    public function lacksPermission(): bool
    {
        return $this->status === 403 && $this->error === 'sin_permiso';
    }
}
