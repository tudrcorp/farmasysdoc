<?php

namespace App\Services\Dolar;

use Illuminate\Support\Facades\Http;
use Throwable;

final class DolarApiEstadoService
{
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(config('dolar.timeout', 8))
                ->acceptJson()
                ->get(rtrim((string) config('dolar.base_url'), '/').'/v1/estado');

            if (! $response->successful()) {
                return false;
            }

            $estado = $response->json('estado');

            // dd(is_string($estado) || strcasecmp(trim($estado), 'Disponible') !== 0);
            return $estado === 'Disponible';
        } catch (Throwable) {
            return false;
        }
    }
}
