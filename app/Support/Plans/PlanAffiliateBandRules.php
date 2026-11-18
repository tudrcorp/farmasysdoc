<?php

namespace App\Support\Plans;

/**
 * Reglas del baremo por cantidad de afiliados: tramos con mínimo ≤ máximo, sin solapes,
 * sin huecos entre tramos consecutivos y un solo tramo abierto ("o más") que debe ser el último.
 */
final class PlanAffiliateBandRules
{
    /**
     * Tramos del baremo comercial estándar de Farmadoc.
     *
     * @var list<array{min_affiliates: int, max_affiliates: int|null}>
     */
    public const STANDARD_BANDS = [
        ['min_affiliates' => 500, 'max_affiliates' => 999],
        ['min_affiliates' => 1000, 'max_affiliates' => 4999],
        ['min_affiliates' => 5000, 'max_affiliates' => 19999],
        ['min_affiliates' => 20000, 'max_affiliates' => 49999],
        ['min_affiliates' => 50000, 'max_affiliates' => 99999],
        ['min_affiliates' => 100000, 'max_affiliates' => null],
    ];

    /**
     * Devuelve el primer error encontrado o null si los tramos son válidos.
     *
     * @param  iterable<array{min_affiliates?: mixed, max_affiliates?: mixed}>  $bands
     */
    public static function firstError(iterable $bands): ?string
    {
        $normalized = self::normalize($bands, strict: true);

        if (is_string($normalized)) {
            return $normalized;
        }

        if ($normalized === []) {
            return 'Agregue al menos un tramo de afiliados.';
        }

        $openEnded = array_filter($normalized, fn (array $band): bool => $band['max'] === null);
        if (count($openEnded) > 1) {
            return 'Solo puede haber un tramo "o más" (sin máximo).';
        }

        for ($index = 1; $index < count($normalized); $index++) {
            $previous = $normalized[$index - 1];
            $current = $normalized[$index];

            if ($previous['max'] === null || $current['min'] <= $previous['max']) {
                return 'Los tramos '.self::describe($previous['min'], $previous['max']).' y '.self::describe($current['min'], $current['max']).' se solapan.';
            }

            if ($current['min'] > $previous['max'] + 1) {
                return 'Hay un hueco entre los tramos '.self::describe($previous['min'], $previous['max']).' y '.self::describe($current['min'], $current['max'])
                    .': falta cubrir '.self::describe($previous['max'] + 1, $current['min'] - 1).' afiliados.';
            }
        }

        return null;
    }

    /**
     * Columnas cuyo precio sube al pasar a un tramo con más afiliados (aviso no bloqueante).
     *
     * @param  iterable<array{min_affiliates?: mixed, max_affiliates?: mixed, prices?: mixed}>  $bands
     * @param  array<string, string>  $columns  clave de precio => nombre de la columna
     * @return list<string>
     */
    public static function priceWarnings(iterable $bands, array $columns): array
    {
        $rows = [];
        foreach ($bands as $band) {
            if (is_numeric($band['min_affiliates'] ?? null)) {
                $rows[] = $band;
            }
        }

        usort($rows, fn (array $a, array $b): int => (int) $a['min_affiliates'] <=> (int) $b['min_affiliates']);

        $warnings = [];
        foreach ($columns as $key => $name) {
            for ($index = 1; $index < count($rows); $index++) {
                $previous = $rows[$index - 1]['prices'][$key] ?? null;
                $current = $rows[$index]['prices'][$key] ?? null;

                if (is_numeric($previous) && is_numeric($current) && (float) $current > (float) $previous) {
                    $warnings[] = "{$name}: el tramo ".self::describe((int) $rows[$index]['min_affiliates'], is_numeric($rows[$index]['max_affiliates'] ?? null) ? (int) $rows[$index]['max_affiliates'] : null)
                        .' es más caro que el anterior.';
                }
            }
        }

        return $warnings;
    }

    /**
     * Tramo (de los datos del formulario) que cubre la cantidad de afiliados.
     *
     * @param  iterable<array{min_affiliates?: mixed, max_affiliates?: mixed, prices?: mixed}>  $bands
     * @return array{min_affiliates?: mixed, max_affiliates?: mixed, prices?: mixed}|null
     */
    public static function bandForCount(iterable $bands, int $affiliates): ?array
    {
        foreach ($bands as $band) {
            if (! is_numeric($band['min_affiliates'] ?? null)) {
                continue;
            }

            $max = $band['max_affiliates'] ?? null;
            if ($affiliates >= (int) $band['min_affiliates'] && (! is_numeric($max) || $affiliates <= (int) $max)) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Menor cantidad de afiliados contratable (mínimo del primer tramo).
     *
     * @param  iterable<array{min_affiliates?: mixed}>  $bands
     */
    public static function minimumAffiliates(iterable $bands): ?int
    {
        $minimums = [];
        foreach ($bands as $band) {
            if (is_numeric($band['min_affiliates'] ?? null)) {
                $minimums[] = (int) $band['min_affiliates'];
            }
        }

        return $minimums === [] ? null : min($minimums);
    }

    public static function describe(int $min, ?int $max): string
    {
        $format = fn (int $value): string => number_format($value, 0, ',', '.');

        return $max === null ? $format($min).' o más' : $format($min).' - '.$format($max);
    }

    /**
     * @param  iterable<array{min_affiliates?: mixed, max_affiliates?: mixed}>  $bands
     * @return list<array{min: int, max: int|null}>|string
     */
    private static function normalize(iterable $bands, bool $strict): array|string
    {
        $normalized = [];

        foreach ($bands as $band) {
            $min = $band['min_affiliates'] ?? null;
            $max = $band['max_affiliates'] ?? null;

            if (! is_numeric($min)) {
                if ($strict) {
                    return 'Cada tramo debe tener la cantidad mínima de afiliados.';
                }

                continue;
            }

            $min = (int) $min;
            $max = is_numeric($max) ? (int) $max : null;

            if ($min < 1) {
                return 'La cantidad mínima de afiliados debe ser al menos 1.';
            }

            if ($max !== null && $max < $min) {
                return 'El tramo '.self::describe($min, $max).' tiene el máximo menor que el mínimo.';
            }

            $normalized[] = ['min' => $min, 'max' => $max];
        }

        usort($normalized, fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        return $normalized;
    }
}
