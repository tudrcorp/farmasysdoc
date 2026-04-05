<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IVA por defecto en líneas de pedido (%)
    |--------------------------------------------------------------------------
    |
    | Se aplica solo a productos con «Grava IVA» activo en el catálogo.
    |
    */
    'default_vat_rate_percent' => (float) env('ORDERS_DEFAULT_VAT_RATE_PERCENT', 19),

    /*
    |--------------------------------------------------------------------------
    | QR de pago (pedidos aliado, de contado)
    |--------------------------------------------------------------------------
    |
    | Rutas relativas a `public/` para las imágenes mostradas en el formulario.
    |
    */
    'partner_payment_qr' => [
        'pago_movil' => env('ORDER_QR_PAGO_MOVIL', 'images/pagos/qr_pago_movil.jpeg'),
        'zelle' => env('ORDER_QR_ZELLE', 'images/pagos/qr_zelle.jpg'),
    ],

];
