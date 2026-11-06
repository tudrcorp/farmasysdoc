<?php

namespace App\Services\Purchases;

use RuntimeException;

/**
 * Regla de negocio de devoluciones incumplida; el mensaje (en español) se muestra tal cual.
 */
final class SupplierReturnException extends RuntimeException {}
