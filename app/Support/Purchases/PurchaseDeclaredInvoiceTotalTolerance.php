<?php

namespace App\Support\Purchases;

/**
 * Coincidencia del total declarado por el usuario vs total calculado por líneas.
 *
 * Se aceptan diferencias solo en la parte decimal: la distancia entre ambos montos
 * debe ser estrictamente menor a 1 unidad monetaria (p. ej. 124556,56 vs 124556,80 sí;
 * 124556,56 vs 124557,56 no).
 */
final class PurchaseDeclaredInvoiceTotalTolerance
{
    public static function matches(float $declared, float $calculated): bool
    {
        return abs($declared - $calculated) < 1.0;
    }
}
