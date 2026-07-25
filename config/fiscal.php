<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agente de retención (Retenciones / SENIAT)
    |--------------------------------------------------------------------------
    */
    'retention_agent' => [
        'name' => env('FISCAL_RETENTION_AGENT_NAME', 'VEN MEDICAL GLOBAL,C.A.'),
        'rif' => env('FISCAL_RETENTION_AGENT_RIF', 'J-41086765-5'),
        'address' => env(
            'FISCAL_RETENTION_AGENT_ADDRESS',
            'AV CIUDAD VARYNA CASA NRO G220 URB CIUDAD VARYNA SECTOR CEIBA BARINAS',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Número de comprobante de Retenciones
    |--------------------------------------------------------------------------
    |
    | Primer valor asignado cuando aún no hay filas en purchase_books.
    |
    */
    'purchase_book' => [
        'initial_voucher_number' => (int) env('FISCAL_PURCHASE_BOOK_INITIAL_VOUCHER', 20260700000058),
    ],

];
