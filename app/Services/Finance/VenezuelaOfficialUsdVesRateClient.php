<?php

namespace App\Services\Finance;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Tasas históricas USD/VES (promedio oficial) desde DolarAPI Venezuela.
 *
 * @see https://ve.dolarapi.com/v1/historicos/dolares/oficial
 */
final class VenezuelaOfficialUsdVesRateClient
{
    private const ENDPOINT = 'https://ve.dolarapi.com/v1/historicos/dolares/oficial';

    private const CACHE_KEY = 'finance.ve.dolarapi.oficial_historico';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Bs por 1 USD (campo {@code promedio}) para la fecha de factura: coincidencia exacta
     * o, si no existe, la cotización más reciente con fecha menor o igual a la pedida.
     */
    public function rateForDate(CarbonInterface|string|null $invoiceDate): ?float
    {
        if ($invoiceDate === null || $invoiceDate === '') {
            return null;
        }

        $target = $invoiceDate instanceof CarbonInterface
            ? $invoiceDate->copy()->startOfDay()
            : Carbon::parse((string) $invoiceDate)->startOfDay();

        $rows = $this->fetchRows();
        if ($rows === []) {
            return null;
        }

        $targetKey = $target->toDateString();

        foreach ($rows as $row) {
            $fecha = (string) ($row['fecha'] ?? '');
            if ($fecha === $targetKey) {
                return self::normalizePromedio($row['promedio'] ?? null);
            }
        }

        $bestDate = null;
        $bestRate = null;

        foreach ($rows as $row) {
            $fecha = (string) ($row['fecha'] ?? '');
            if ($fecha === '') {
                continue;
            }
            try {
                $d = Carbon::parse($fecha)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($d->greaterThan($target)) {
                continue;
            }
            if ($bestDate === null || $d->greaterThan($bestDate)) {
                $bestDate = $d;
                $bestRate = self::normalizePromedio($row['promedio'] ?? null);
            }
        }

        return $bestRate;
    }

    /**
     * @return list<array{fecha?: string, promedio?: float|int|string|null}>
     */
    private function fetchRows(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(self::ENDPOINT);

            if (! $response->successful()) {
                return [];
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                return [];
            }

            /** @var list<array<string, mixed>> $list */
            $list = array_values($decoded);

            usort($list, function (array $a, array $b): int {
                return strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
            });

            return $list;
        });
    }

    private static function normalizePromedio(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $f = round((float) $value, 2);

        return $f > 0 ? $f : null;
    }
}
