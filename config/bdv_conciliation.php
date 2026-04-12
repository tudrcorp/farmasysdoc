<?php

/**
 * Configuración de APIs REST del Banco de Venezuela (conciliación, saldos, C2P, etc.).
 *
 * Documentación de referencia: manuales «Dummy» ambiente CALIDAD (BDV). Cada familia de
 * servicios puede exigir una API Key distinta en QA; en producción las claves las asigna el banco.
 *
 * Host QA documentado: https://bdvconciliacionqa.banvenez.com:444
 * Producción documentada: https://bdvconciliacion.banvenez.com:443
 *
 * La IP 200.11.243.176 (manual MDU antiguo) suele provocar cURL 52 en casi todos los paths REST actuales.
 * Esa corrección se aplica a **todos** los endpoints (misma base_url por entorno: conciliación, saldo, C2P, lote…).
 * Para forzar el uso de la IP: BDV_QA_USE_LEGACY_IP_HOST=true en .env (afecta QA y producción si apuntan a esa IP).
 */
$allowLegacyBdvIp = filter_var(env('BDV_QA_USE_LEGACY_IP_HOST', false), FILTER_VALIDATE_BOOL);

/**
 * Resuelve base URL: prioriza $primary, luego $fallback, luego $defaultHttps; si el host es la IP legacy y no está
 * permitido explícitamente, devuelve $defaultHttps (misma regla para calidad y producción).
 */
$resolveBdvBaseUrl = static function (
    ?string $primary,
    ?string $fallback,
    string $defaultHttps,
    bool $allowLegacyIp,
): string {
    $candidate = $primary !== null && $primary !== ''
        ? (string) $primary
        : (($fallback !== null && $fallback !== '') ? (string) $fallback : $defaultHttps);

    $candidate = rtrim($candidate, '/');
    $host = parse_url($candidate, PHP_URL_HOST);

    if ($host === '200.11.243.176' && ! $allowLegacyIp) {
        return rtrim($defaultHttps, '/');
    }

    return $candidate;
};

$defaultQaHttps = 'https://bdvconciliacionqa.banvenez.com:444';
$defaultProductionHttps = 'https://bdvconciliacion.banvenez.com:443';

$qaBaseResolved = $resolveBdvBaseUrl(
    env('BDV_QA_BASE_URL'),
    env('BDV_CONCILIATION_QA_BASE_URL'),
    $defaultQaHttps,
    $allowLegacyBdvIp,
);

$productionBaseResolved = $resolveBdvBaseUrl(
    env('BDV_PRODUCTION_BASE_URL'),
    env('BDV_CONCILIATION_BASE_URL'),
    $defaultProductionHttps,
    $allowLegacyBdvIp,
);

return [

    'timeout' => (int) env('BDV_CONCILIATION_TIMEOUT', 30),

    'connect_timeout' => (int) env('BDV_CONCILIATION_CONNECT_TIMEOUT', 15),

    /*
     * Forzar IPv4 evita fallos de cURL cuando no hay ruta IPv6 hacia el banco.
     */
    'force_ipv4' => filter_var(env('BDV_CONCILIATION_FORCE_IPV4', true), FILTER_VALIDATE_BOOL),

    /*
     * Cadena vacía = no enviar User-Agent (más parecido a un cURL mínimo de Postman). Por defecto se envía uno identificable.
     */
    'user_agent' => (string) env('BDV_CONCILIATION_USER_AGENT', 'Farmadoc/1.0 (BDV API)'),

    /**
     * Rutas relativas al base_url del entorno (deben empezar por /).
     * Puedes sobreescribir la de conciliación si el banco cambia la versión del path.
     */
    'paths' => [
        'get_movement' => (string) env('BDV_PATH_GET_MOVEMENT', '/getMovement/v2'),
        'consulta_multiple' => '/api/consulta/consultaMultiple/v2',
        'account_balances' => '/account/balances/v2',
        'consulta_movimientos' => '/apis/bdv/consulta/movimientos/v2',
        'get_out_movement' => '/getOutMovement/v2',
        'vuelto' => '/api/vuelto/v2',
        'c2p_payment_key' => '/BankMobilePaymentC2P/MultipleAccounts/paymentkey/v2',
        'c2p_process' => '/BankMobilePaymentC2P/MultipleAccounts/process/v2',
        'c2p_annulment' => '/BankMobilePaymentC2P/MultipleAccounts/annulment/v2',
        'pagomovil_lote' => '/v2/pagomovil/notificaciones/lotes/v2',
    ],

    /**
     * Por entorno: URL base + tres «ranuras» de credencial usadas en los manuales dummy QA.
     *
     * - conciliation: conciliación simple getMovement/v2 (manual API Conciliación).
     * - suite: saldo, movimientos, conciliación múltiple, salientes, vuelto, C2P (misma key en dummy QA).
     * - lote: consulta pago móvil por ventana 15 min (manual indica otra key en Swagger).
     */
    'environments' => [

        'qa' => [
            'label' => 'Calidad / pruebas',
            'base_url' => $qaBaseResolved,
            'keys' => [
                'conciliation' => (string) env(
                    'BDV_QA_KEY_CONCILIATION',
                    env('BDV_CONCILIATION_QA_API_KEY', '96R7T1T5J2134T5YFC2GF15SDFG4BD1Z')
                ),
                'suite' => (string) env('BDV_QA_KEY_SUITE', '256D0FDD36F1B1B3F1208A9B6EC693'),
                'lote' => (string) env('BDV_QA_KEY_LOTE', '290219AF407EE0816404A78814DD7F1E656F828D'),
            ],
        ],

        'production' => [
            'label' => 'Producción',
            'base_url' => $productionBaseResolved,
            'keys' => [
                'conciliation' => env('BDV_PRODUCTION_KEY_CONCILIATION', env('BDV_CONCILIATION_API_KEY')),
                'suite' => env('BDV_PRODUCTION_KEY_SUITE'),
                'lote' => env('BDV_PRODUCTION_KEY_LOTE'),
            ],
        ],
    ],

];
