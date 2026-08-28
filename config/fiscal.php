<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agente de retención (Retenciones / SENIAT)
    |--------------------------------------------------------------------------
    |
    | La dirección fiscal de la empresa también se puede editar en Farmaadmin
    | (Datos fiscales de la empresa). Si esa fila está vacía, se usa este valor.
    |
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
    | Formato: YYYY + MM + secuencia de 8 dígitos (p. ej. 20260800000120).
    | La secuencia es por mes de la factura. Este valor es el primero del mes
    | que coincida con el prefijo YYYYMM; los demás meses arrancan en 00000001
    | (p. ej. enero 2027 → 20270100000001).
    |
    */
    'purchase_book' => [
        'initial_voucher_number' => (int) env('FISCAL_PURCHASE_BOOK_INITIAL_VOUCHER', 20260800000120),
    ],

];
