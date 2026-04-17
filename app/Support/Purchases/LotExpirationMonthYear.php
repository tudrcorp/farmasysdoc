<?php

namespace App\Support\Purchases;

/**
 * Vencimiento de lote en compras: formato fijo mm/YYYY (ej. 08/2026).
 */
final class LotExpirationMonthYear
{
    public const REGEX = '/^(0[1-9]|1[0-2])\/\d{4}$/';

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }

    public static function isValidFormat(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return (bool) preg_match(self::REGEX, $value);
    }
}
