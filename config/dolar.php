<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de tasas USD → VES (ve.dolarapi.com)
    |--------------------------------------------------------------------------
    */

    'base_url' => env('DOLAR_API_BASE_URL', 'https://ve.dolarapi.com'),

    'timeout' => (int) env('DOLAR_API_TIMEOUT', 8),

];
