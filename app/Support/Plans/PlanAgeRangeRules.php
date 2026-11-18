<?php

namespace App\Support\Plans;

/**
 * Reglas de consistencia de los rangos de edad de un plan: mínimo ≤ máximo, sin solapes
 * y un solo rango abierto ("en adelante") que debe ser el último.
 */
final class PlanAgeRangeRules
{
    public const MAX_AGE = 120;

    /**
     * Devuelve el primer error encontrado o null si los rangos son válidos.
     *
     * @param  iterable<array{min_age?: mixed, max_age?: mixed}>  $ranges
     */
    public static function firstError(iterable $ranges): ?string
    {
        $normalized = [];

        foreach ($ranges as $range) {
            $min = $range['min_age'] ?? null;
            $max = $range['max_age'] ?? null;

            if (! is_numeric($min)) {
                return 'Cada rango debe tener una edad mínima.';
            }

            $min = (int) $min;
            $max = is_numeric($max) ? (int) $max : null;

            if ($min < 0 || $min > self::MAX_AGE || ($max !== null && $max > self::MAX_AGE)) {
                return 'Las edades deben estar entre 0 y '.self::MAX_AGE.' años.';
            }

            if ($max !== null && $max < $min) {
                return "El rango {$min} a {$max} años tiene la edad máxima menor que la mínima.";
            }

            $normalized[] = ['min' => $min, 'max' => $max];
        }

        if ($normalized === []) {
            return 'Agregue al menos un rango de edad.';
        }

        usort($normalized, fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        $openEnded = array_filter($normalized, fn (array $range): bool => $range['max'] === null);
        if (count($openEnded) > 1) {
            return 'Solo puede haber un rango "en adelante" (sin edad máxima).';
        }

        for ($index = 1; $index < count($normalized); $index++) {
            $previous = $normalized[$index - 1];
            $current = $normalized[$index];

            if ($previous['max'] === null || $current['min'] <= $previous['max']) {
                return 'Los rangos '.self::describe($previous).' y '.self::describe($current).' se solapan.';
            }
        }

        return null;
    }

    /**
     * Huecos entre rangos consecutivos (edades que ningún rango cubre), como aviso no bloqueante.
     *
     * @param  iterable<array{min_age?: mixed, max_age?: mixed}>  $ranges
     * @return list<string>
     */
    public static function gaps(iterable $ranges): array
    {
        $normalized = [];
        foreach ($ranges as $range) {
            if (! is_numeric($range['min_age'] ?? null)) {
                continue;
            }
            $normalized[] = [
                'min' => (int) $range['min_age'],
                'max' => is_numeric($range['max_age'] ?? null) ? (int) $range['max_age'] : null,
            ];
        }

        usort($normalized, fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        $gaps = [];
        if ($normalized !== [] && $normalized[0]['min'] > 0) {
            $gaps[] = self::describe(['min' => 0, 'max' => $normalized[0]['min'] - 1]);
        }

        for ($index = 1; $index < count($normalized); $index++) {
            $previousMax = $normalized[$index - 1]['max'];
            if ($previousMax !== null && $normalized[$index]['min'] > $previousMax + 1) {
                $gaps[] = self::describe(['min' => $previousMax + 1, 'max' => $normalized[$index]['min'] - 1]);
            }
        }

        $last = $normalized === [] ? null : $normalized[count($normalized) - 1];
        if ($last !== null && $last['max'] !== null && $last['max'] < self::MAX_AGE) {
            $gaps[] = ($last['max'] + 1).' años en adelante';
        }

        return $gaps;
    }

    /**
     * @param  array{min: int, max: int|null}  $range
     */
    private static function describe(array $range): string
    {
        if ($range['max'] === null) {
            return "{$range['min']} años en adelante";
        }

        return $range['min'] === $range['max']
            ? "{$range['min']} años"
            : "{$range['min']} a {$range['max']} años";
    }
}
