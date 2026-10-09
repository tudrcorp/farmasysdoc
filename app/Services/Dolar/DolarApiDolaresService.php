<?php

namespace App\Services\Dolar;

use App\Support\Finance\BcvRate;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DolarApiDolaresService
{
    /**
     * Obtiene el promedio oficial USD (BCV) desde /v1/dolares.
     */
    public function getOfficialUsdToVesRate(): ?float
    {
        $payload = $this->getOfficialUsdToVesRatePayload();

        return $payload['rate'] ?? null;
    }

    /**
     * Tasa oficial BCV truncada a 2 decimales ({@see BcvRate}) y su representación para mostrar.
     *
     * @return array{rate: float, display: string}|null
     */
    public function getOfficialUsdToVesRatePayload(): ?array
    {
        try {
            $response = Http::timeout(config('dolar.timeout', 8))
                ->acceptJson()
                ->get(rtrim((string) config('dolar.base_url'), '/').'/v1/dolares');

            if (! $response->successful()) {
                return null;
            }

            $items = $response->json();
            if (! is_array($items)) {
                return null;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (($item['moneda'] ?? null) === 'USD' && ($item['fuente'] ?? null) === 'oficial') {
                    $promedio = $item['promedio'] ?? null;
                    if (! is_numeric($promedio)) {
                        return null;
                    }

                    $rate = BcvRate::truncateOrNull($promedio);
                    if ($rate === null) {
                        return null;
                    }

                    $display = BcvRate::format($rate);

                    return [
                        'rate' => $rate,
                        'display' => $display,
                    ];
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
