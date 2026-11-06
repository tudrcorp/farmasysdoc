<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Avisos de vencimiento a administradores
    |--------------------------------------------------------------------------
    |
    | Días calendario antes del vencimiento en los que se avisa (correo, WhatsApp y panel)
    | con un PDF de las facturas por pagar. Cada factura se avisa una vez por umbral.
    |
    */

    'due_notice_days' => [5, 3],

    'due_notice_time' => env('ACCOUNTS_PAYABLE_DUE_NOTICE_TIME', '07:00'),

    'due_notice_disk' => 'local',

    'due_notice_directory' => 'accounts-payable/due-notices',

];
