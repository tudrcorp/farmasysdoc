<?php

namespace App\Services\Dolar;

use Illuminate\Support\Facades\Http;
use Throwable;

final class DolarApiDolaresService
{
    /**
     * Obtiene el promedio oficial USD (BCV) desde /v1/dolares.
     */
    public function getOfficialUsdToVesRate(): ?float
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
                    if (is_numeric($promedio)) {
                        $rate = (float) $promedio;

                        return $rate > 0 ? $rate : null;
                    }
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
