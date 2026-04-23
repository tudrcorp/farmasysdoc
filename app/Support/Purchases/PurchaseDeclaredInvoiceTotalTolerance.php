<?php

namespace App\Support\Purchases;

/**
 * Coincidencia del total declarado por el usuario vs total calculado por líneas.
 *
 * Tras redondear a 2 decimales (centavos), solo puede diferir la última cifra del monto:
 * la parte entera y la primera cifra decimal deben coincidir. Equivale a que ambos
 * montos comparten el mismo cociente entdiv(centavos, 10) (mismo “bloque” de 0,10).
 *
 * Ej.: 1234,67 vs 1234,68 sí; 1234,67 vs 1234,77 no (cambia la décima).
 * Dentro del bloque, la diferencia máxima es 0,09 (9 centésimas de unidad).
 */
final class PurchaseDeclaredInvoiceTotalTolerance
{
    public static function matches(float $declared, float $calculated): bool
    {
        $centsDeclared = (int) round($declared * 100);
        $centsCalculated = (int) round($calculated * 100);

        return intdiv($centsDeclared, 10) === intdiv($centsCalculated, 10);
    }
}
