<?php

namespace App\Services\BdvConciliation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class BdvConciliationClient
{
    /**
     * @param  array<string, string>  $payload
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ConnectionException
     */
    public function postGetMovement(array $payload, string $environment = 'qa'): Response
    {
        $environment = strtolower($environment);
        $config = config('bdv_conciliation.environments.'.$environment);

        if (! is_array($config)) {
            throw new InvalidArgumentException('Entorno BDV no válido. Use qa o production.');
        }

        $baseUrl = $config['base_url'] ?? '';
        $apiKey = $config['api_key'] ?? null;

        if ($baseUrl === '') {
            throw new RuntimeException("Falta base_url para el entorno [{$environment}].");
        }

        if ($environment === 'production' && blank($apiKey)) {
            throw new RuntimeException('Configure BDV_CONCILIATION_API_KEY para llamar a producción.');
        }

        if (blank($apiKey)) {
            throw new RuntimeException("Falta API Key para el entorno [{$environment}].");
        }

        $url = $baseUrl.'/getMovement';

        $pending = Http::timeout((int) config('bdv_conciliation.timeout', 30))
            ->connectTimeout((int) config('bdv_conciliation.connect_timeout', 15))
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => (string) config('bdv_conciliation.user_agent', 'Farmadoc/1.0 (BDV Conciliation)'),
                'Connection' => 'close',
            ]);

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

    private static function connectionHints(string $url, string $curlMessage): string
    {
        $lines = [
            'Sugerencias (error de conexión hacia el banco):',
            '• cURL 52 "Empty reply from server": el servidor cerró la conexión sin enviar cabeceras HTTP. Suele ser servicio de pruebas caído, IP o puerto bloqueado por firewall, o que solo respondan desde ciertas redes (p. ej. Venezuela).',
            '• Comprueba conectividad: desde la misma máquina ejecuta `curl -v -X POST \''.$url.'\' -H \'Content-Type: application/json\' -H \'X-API-Key: …\' -d \'{}\'`.',
            '• Si estás fuera del país, prueba VPN o un servidor en la región que el banco indique.',
            '• Verifica con BDV si la URL/puerto de calidad sigue vigente (el manual MDU-006 puede quedar desactualizado).',
            '• Puedes desactivar forzar IPv4 en .env: BDV_CONCILIATION_FORCE_IPV4=false por si tu red requiere IPv6.',
        ];

        if (str_contains($curlMessage, 'SSL') || str_contains($curlMessage, 'certificate')) {
            $lines[] = '• Error SSL: en producción use la URL https documentada; no desactive verificación salvo indicación del banco.';
        }

        return implode(PHP_EOL, $lines);
    }
}
