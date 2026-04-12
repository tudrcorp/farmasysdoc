<?php

namespace App\Services\BdvConciliation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cliente HTTP genérico hacia las APIs REST documentadas por BDV.
 *
 * Importante: el cuerpo debe ir como JSON real (método asJson() del cliente HTTP de Laravel). Si solo se pone
 * Content-Type: application/json y se envía un array con post(), Guzzle puede mandar form-urlencoded y el
 * banco responde error. El header de clave se envía como «x-api-key» en minúsculas, igual que Postman/cURL
 * de ejemplo del banco (algunos entornos son estrictos con el nombre del header).
 */
final class BdvBankApiClient
{
    /**
     * POST JSON al path indicado (relativo al base_url del entorno).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidArgumentException Entorno desconocido
     * @throws RuntimeException Falta URL base o API Key
     * @throws ConnectionException Error de red
     */
    public function postJson(string $path, array $payload, string $environment, string $keySlot = 'suite'): Response
    {
        $environment = strtolower($environment);
        /** @var array<string, mixed>|null $envConfig */
        $envConfig = config('bdv_conciliation.environments.'.$environment);

        if (! is_array($envConfig)) {
            throw new InvalidArgumentException('Entorno BDV no válido. Use qa o production.');
        }

        $baseUrl = isset($envConfig['base_url']) ? (string) $envConfig['base_url'] : '';
        if ($baseUrl === '') {
            throw new RuntimeException("Falta base_url para el entorno [{$environment}].");
        }

        /** @var array<string, string|null> $keys */
        $keys = is_array($envConfig['keys'] ?? null) ? $envConfig['keys'] : [];
        $apiKey = $keys[$keySlot] ?? null;

        if ($environment === 'production' && blank($apiKey)) {
            throw new RuntimeException(
                "Configure la API Key de producción para la ranura [{$keySlot}] (variables BDV_PRODUCTION_KEY_* en .env)."
            );
        }

        if (blank($apiKey)) {
            throw new RuntimeException("Falta API Key para la ranura [{$keySlot}] en entorno [{$environment}].");
        }

        $path = '/'.ltrim($path, '/');
        $url = $baseUrl.$path;

        /*
         * asJson(): serializa el array a JSON y fija Content-Type application/json (coherente con Postman).
         * Sin esto, post($url, $array) suele usar application/x-www-form-urlencoded aunque se haya puesto
         * Content-Type json a mano, y BDV devuelve error.
         */
        $pending = Http::timeout((int) config('bdv_conciliation.timeout', 30))
            ->connectTimeout((int) config('bdv_conciliation.connect_timeout', 15))
            ->asJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
            ]);

        $userAgent = trim((string) config('bdv_conciliation.user_agent', ''));
        if ($userAgent !== '') {
            $pending = $pending->withHeaders(['User-Agent' => $userAgent]);
        }

        if (config('bdv_conciliation.force_ipv4', true)) {
            $pending = $pending->withOptions([
                'curl' => [
                    \CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4,
                ],
            ]);
        }

        try {
            return $pending->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new ConnectionException(
                $e->getMessage().PHP_EOL.PHP_EOL.self::connectionHints($url, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * Resuelve un path declarado en config/bdv_conciliation.php → paths.<clave>.
     */
    public function path(string $configKey): string
    {
        $paths = config('bdv_conciliation.paths', []);
        if (! is_array($paths) || ! isset($paths[$configKey])) {
            throw new InvalidArgumentException("Path BDV desconocido en config: {$configKey}");
        }

        return (string) $paths[$configKey];
    }

    private static function connectionHints(string $url, string $curlMessage): string
    {
        $lines = [
            'Sugerencias (error de conexión hacia el banco):',
            '• Firewall, VPN o geolocalización: el host de calidad puede no responder fuera de la red esperada.',
            '• Prueba: curl -v -X POST \''.$url.'\' -H \'Content-Type: application/json\' -H \'x-api-key: …\' -d \'{"currency":"VES","account":"…"}\'',
            '• cURL 52 contra 200.11.243.176: esa IP del MDU antiguo suele cerrar sin HTTP en /account/balances, etc. Usa https://bdvconciliacionqa.banvenez.com:444 (BDV_QA_BASE_URL).',
        ];

        if (str_contains($curlMessage, 'SSL') || str_contains($curlMessage, 'certificate')) {
            $lines[] = '• Error SSL: use la URL https documentada; no desactive la verificación salvo indicación del banco.';
        }

        return implode(PHP_EOL, $lines);
    }
}
