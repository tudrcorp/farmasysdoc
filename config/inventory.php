<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alertas FEFO en caja (productos con vencimiento en compra)
    |--------------------------------------------------------------------------
    */

    'fefo_pos_alerts_enabled' => true,

    'lot_near_expiry_days' => [
        'critical' => (int) env('INVENTORY_LOT_CRITICAL_DAYS', 30),
        'warning' => (int) env('INVENTORY_LOT_WARNING_DAYS', 60),
    ],

    'fefo_alert_log_dedupe_seconds' => (int) env('INVENTORY_FEFO_ALERT_LOG_DEDUPE_SECONDS', 90),

    'fefo_alert_sale_link_hours' => (int) env('INVENTORY_FEFO_ALERT_SALE_LINK_HOURS', 4),

    /*
    |--------------------------------------------------------------------------
    | Importación FEFO desde CSV (compra de apertura INV-FEFO-APERTURA-{branch})
    |--------------------------------------------------------------------------
    */

    'fefo_seed_supplier_id' => env('INVENTORY_FEFO_SEED_SUPPLIER_ID') !== null
        ? (int) env('INVENTORY_FEFO_SEED_SUPPLIER_ID')
        : null,

];
