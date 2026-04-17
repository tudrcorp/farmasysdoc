<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IVA por defecto en líneas de pedido (%)
    |--------------------------------------------------------------------------
    |
    | Se aplica solo a productos con «Grava IVA» activo en el catálogo.
    | El valor efectivo lo define la tabla financial_settings (pantalla Administración financiera);
    | esta clave solo sirve de respaldo si no hay registro o en entornos sin migración.
    |
    */
    'default_vat_rate_percent' => (float) env('ORDERS_DEFAULT_VAT_RATE_PERCENT', 16),

    /*
    |--------------------------------------------------------------------------
    | IGTF por defecto (%)
    |--------------------------------------------------------------------------
    |
    | Sobre el total de la factura (neto + IVA) cuando el cobro es efectivo en USD.
    | El valor efectivo lo define financial_settings (Administración financiera).
    |
    */
    'default_igtf_rate_percent' => (float) env('ORDERS_DEFAULT_IGTF_RATE_PERCENT', 3),

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
