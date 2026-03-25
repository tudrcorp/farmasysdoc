<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ancho del ticket (caracteres por línea)
    |--------------------------------------------------------------------------
    | Típico: 58 mm ≈ 32–42; 80 mm ≈ 42–48. Ajuste según su impresora HKA/ACLAS.
    */
    'thermal_line_width' => (int) env('FISCAL_THERMAL_LINE_WIDTH', 42),

    /*
    |--------------------------------------------------------------------------
    | Tasa Bs/USD de respaldo
    |--------------------------------------------------------------------------
    | Si la venta no permite inferir tasa (p. ej. solo USD sin VES), se usa para
    | mostrar montos en bolívares en el ticket de prueba.
    */
    'fallback_ves_usd_rate' => env('FISCAL_VES_USD_RATE') !== null
        ? (float) env('FISCAL_VES_USD_RATE')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Datos de pie fiscal (hardware / emisor)
    |--------------------------------------------------------------------------
    */
    'printer_serial' => env('FISCAL_PRINTER_SERIAL', 'ZZP0000000'),

    'emitido_por' => env('FISCAL_EMITIDO_POR', 'FARMASYS'),

    'mh_footer' => env('FISCAL_MH_FOOTER', 'MH'),

];
