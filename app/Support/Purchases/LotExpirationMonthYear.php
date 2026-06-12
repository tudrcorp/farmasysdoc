<?php

namespace App\Support\Purchases;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

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

    /**
     * Clave de orden FEFO (año-mes) para comparaciones en PHP o SQL derivado.
     */
    public static function sortKey(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === null || ! self::isValidFormat($normalized)) {
            return null;
        }

        [$month, $year] = explode('/', $normalized);

        return $year.'-'.$month;
    }

    /**
     * Último día del mes de vencimiento (fin de vigencia del lote).
     */
    public static function toEndOfMonthDate(?string $value): ?CarbonImmutable
    {
        $normalized = self::normalize($value);
        if ($normalized === null || ! self::isValidFormat($normalized)) {
            return null;
        }

        [$month, $year] = explode('/', $normalized);

        return CarbonImmutable::createFromDate((int) $year, (int) $month, 1)
            ->endOfMonth()
            ->startOfDay();
    }

    /**
     * Días hasta el fin del mes de vencimiento (negativo si ya venció).
     */
    public static function daysUntilExpiry(?string $value, ?CarbonImmutable $today = null): ?int
    {
        $endOfMonth = self::toEndOfMonthDate($value);
        if ($endOfMonth === null) {
            return null;
        }

        $today ??= CarbonImmutable::today();

        return (int) $today->diffInDays($endOfMonth, false);
    }

    /**
     * Expresión SQL para orden FEFO (MySQL/MariaDB).
     */
    public static function mysqlOrderByExpression(string $column): string
    {
        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
            throw new InvalidArgumentException('Columna SQL inválida para orden FEFO.');
        }

        return "STR_TO_DATE(CONCAT('01/', {$column}), '%d/%m/%Y')";
    }
}
