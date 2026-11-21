<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de medicamentos no cubiertos (Tu Dr. Group)
    |--------------------------------------------------------------------------
    |
    | Consulta de solo lectura. El token de cada aliado se guarda cifrado en
    | la base y nunca se pone en la URL. La tabla del panel no llama a esta
    | API: solo el botón «Actualizar información» dispara la sincronización.
    |
    */

    'base_url' => env('PARTNER_UNCOVERED_MEDICINES_BASE_URL', 'https://integracorp.vps.tudrgroup.com/api/v1/aliados'),

    'orders_token' => env('PARTNER_FARMADOC_ORDERS_TOKEN'),

    'timeout' => (int) env('PARTNER_UNCOVERED_MEDICINES_TIMEOUT', 20),

    'per_page' => 100,

    'max_pages' => 500,

    'stale_running_minutes' => 15,

    'rate_limit_retries' => 5,

];
