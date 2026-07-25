<?php

namespace App\Support\Fiscal;

/**
 * Normaliza RIF venezolanos al formato letra-cuerpo-dígito (p. ej. J-41086765-5).
 */
final class VenezuelanRifFormatter
{
    public static function format(?string $taxId): string
    {
        $compact = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $taxId));

        if ($compact === '') {
            return '';
        }

        if (preg_match('/^([VEJGPC])(\d{6,9})(\d)$/', $compact, $matches) === 1) {
            return $matches[1].'-'.$matches[2].'-'.$matches[3];
        }

        if (preg_match('/^([VEJGPC])(\d+)$/', $compact, $matches) === 1 && strlen($matches[2]) >= 2) {
            $body = substr($matches[2], 0, -1);
            $check = substr($matches[2], -1);

            return $matches[1].'-'.$body.'-'.$check;
        }

        return $compact;
    }
}
