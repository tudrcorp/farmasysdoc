<?php

namespace App\Support\Partners;

use InvalidArgumentException;

/**
 * No hay crédito disponible suficiente al validar inicio de entrega o al registrar el consumo al finalizar el pedido.
 */
final class InsufficientPartnerCreditException extends InvalidArgumentException {}
