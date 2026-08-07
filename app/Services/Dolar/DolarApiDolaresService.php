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
        $payload = $this->getOfficialUsdToVesRatePayload();

        return $payload['rate'] ?? null;
    }

    /**
     * Tasa oficial BCV con representación textual de todos los decimales reportados por la API.
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

            $body = $response->body();
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

                    $rate = (float) $promedio;
                    if ($rate <= 0) {
                        return null;
                    }

                    $display = self::extractPromedioLiteralFromBody($body)
                        ?? self::formatAllDecimals($promedio);

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

    /**
     * Extrae el literal decimal de `promedio` del objeto oficial USD en el JSON crudo.
     */
    private static function extractPromedioLiteralFromBody(string $body): ?string
    {
        if (! preg_match_all('/\{[^{}]*\}/', $body, $matches)) {
            return null;
        }

        foreach ($matches[0] as $chunk) {
            if (! str_contains($chunk, '"oficial"') || ! str_contains($chunk, '"USD"')) {
                continue;
            }

            if (preg_match('/"promedio"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $chunk, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * Conserva todos los decimales del valor reportado por la API (sin redondear a escala fija).
     */
    public static function formatAllDecimals(int|float|string $value): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                if (str_contains($trimmed, 'e') || str_contains($trimmed, 'E')) {
                    return self::formatAllDecimals((float) $trimmed);
                }

                return $trimmed;
            }
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $asString = sprintf('%.10F', (float) $value);

        return rtrim(rtrim($asString, '0'), '.') ?: '0';
    }
}
