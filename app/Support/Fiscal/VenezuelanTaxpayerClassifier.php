<?php

namespace App\Support\Fiscal;

/**
 * Distingue contribuyentes (RIF) de consumidores finales (cédula) a partir del documento del cliente.
 *
 * - J, G y C: personas jurídicas, entes públicos y consejos comunales → siempre contribuyentes.
 * - V o E con 9 dígitos (8 + dígito verificador): RIF de persona natural → contribuyente.
 * - V o E con hasta 8 dígitos (cédula), pasaporte o sin documento → no contribuyente.
 */
final class VenezuelanTaxpayerClassifier
{
    public static function compact(?string $document): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $document));
    }

    public static function isTaxpayer(?string $document): bool
    {
        $compact = self::compact($document);

        if (preg_match('/^[JGC]\d{6,9}$/', $compact) === 1) {
            return true;
        }

        return preg_match('/^[VE]\d{9}$/', $compact) === 1;
    }
}
