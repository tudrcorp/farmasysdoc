<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Avisos de vencimiento
    |--------------------------------------------------------------------------
    |
    | Días antes de la fecha de fin en los que se avisa a los involucrados.
    | Cada umbral se envía una sola vez por convenio.
    |
    */

    'expiry_notice_days' => [60, 30, 15, 7, 1],

    /*
    |--------------------------------------------------------------------------
    | Contrato firmado
    |--------------------------------------------------------------------------
    */

    'document_disk' => 'local',

    'document_directory' => 'partner-companies/agreements',

    'document_accepted_mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],

    'document_max_size_kb' => 10240,

    /*
    |--------------------------------------------------------------------------
    | Proceso diario (vencimientos, renovaciones automáticas y entradas en vigencia)
    |--------------------------------------------------------------------------
    */

    'daily_run_at' => env('PARTNER_AGREEMENTS_DAILY_RUN_AT', '00:30'),

];
