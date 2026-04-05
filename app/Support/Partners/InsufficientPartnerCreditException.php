<?php

namespace App\Support\Partners;

use InvalidArgumentException;

/**
 * No hay crédito disponible suficiente para asociar el consumo al pasar el pedido a «En proceso».
 */
final class InsufficientPartnerCreditException extends InvalidArgumentException {}
