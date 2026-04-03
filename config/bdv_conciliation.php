<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Conciliación BDV (Pagomóvil — manual MDU-006, enero 2024)
    |--------------------------------------------------------------------------
    |
    | Un único endpoint expuesto por el banco: POST /getMovement
    | - Calidad: base configurable, API Key de pruebas del manual si no defines .env
    | - Producción: URL fija documentada; la API Key la entrega BDVenLínea Empresa
    |
    | Si ves cURL error 52 "Empty reply from server" hacia la IP de calidad: el host cerró el
    | socket sin HTTP (servicio apagado, firewall, bloqueo geográfico, puerto 444 filtrado, etc.).
    | Confirma con el banco URL vigente, VPN/red permitida o prueba desde otra red/servidor.
    |
    */

    'timeout' => (int) env('BDV_CONCILIATION_TIMEOUT', 30),

    'connect_timeout' => (int) env('BDV_CONCILIATION_CONNECT_TIMEOUT', 15),

    /*
    | Forzar IPv4 evita fallos raros de cURL (p. ej. intento IPv6 sin ruta) que a veces se manifiestan
    | como "Empty reply from server" (código 52).
    */
    'force_ipv4' => filter_var(env('BDV_CONCILIATION_FORCE_IPV4', true), FILTER_VALIDATE_BOOL),

    'user_agent' => (string) env('BDV_CONCILIATION_USER_AGENT', 'Farmadoc/1.0 (BDV Conciliation MDU-006)'),

    'environments' => [

        'qa' => [
            'label' => 'Calidad / pruebas',
            'base_url' => rtrim((string) env('BDV_CONCILIATION_QA_BASE_URL', 'http://200.11.243.176:444'), '/'),
            /*
             * Clave de pruebas indicada en el manual (ambiente calidad). Sustituye con .env en tu despliegue si aplica.
             */
            'api_key' => (string) env(
                'BDV_CONCILIATION_QA_API_KEY',
                '96R7T1T5J2134T5YFC2GF15SDFG4BD1Z',
            ),
        ],

        'production' => [
            'label' => 'Producción',
            'base_url' => rtrim((string) env('BDV_CONCILIATION_BASE_URL', 'https://bdvconciliacion.banvenez.com:443'), '/'),
            'api_key' => env('BDV_CONCILIATION_API_KEY'),
        ],
    ],

];
