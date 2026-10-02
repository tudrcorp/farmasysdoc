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
    | Formato: YYYY + MM + secuencia de 8 dígitos (p. ej. 20260900000148).
    | La secuencia es por mes de la factura. Este valor es el primero del mes
    | que coincida con el prefijo YYYYMM; los demás meses arrancan en 00000001
    | (p. ej. enero 2027 → 20270100000001).
    |
    */
    'purchase_book' => [
        'initial_voucher_number' => (int) env('FISCAL_PURCHASE_BOOK_INITIAL_VOUCHER', 20260900000148),
    ],

    /*
    |--------------------------------------------------------------------------
    | Máquinas fiscales (agente local por caja)
    |--------------------------------------------------------------------------
    |
    | La activación es por máquina (modo Desactivada / Simulación / Activa en
    | Farmaadmin): cada caja pasa a facturar con Farmadoc a su ritmo y las demás
    | siguen como hoy.
    |
    | min_simulations: ventas simuladas sin error exigidas antes de activar.
    | continue_after_seconds: segundos tras los cuales el cajero puede seguir
    | vendiendo mientras la factura espera en cola.
    |
    | El agente consulta la cola con long-poll: cada petición queda abierta como
    | máximo «max_wait_seconds» (ocupa un worker PHP por caja mientras espera).
    |
    */
    'printers' => [
        'total_tolerance_ves' => (float) env('FISCAL_PRINTERS_TOTAL_TOLERANCE_VES', 0.05),
        'min_simulations' => (int) env('FISCAL_PRINTERS_MIN_SIMULATIONS', 5),
        'continue_after_seconds' => (int) env('FISCAL_PRINTERS_CONTINUE_AFTER_SECONDS', 8),
        'max_clock_drift_seconds' => (int) env('FISCAL_PRINTERS_MAX_CLOCK_DRIFT_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Laboratorio fiscal
    |--------------------------------------------------------------------------
    |
    | Pruebas pedidas desde Farmaadmin sin crear ventas ni mover inventario.
    | La factura real de prueba queda en la memoria fiscal (se anula con su
    | nota de crédito): su total no puede superar este monto en Bs.
    |
    */
    'test_lab' => [
        'max_invoice_total_ves' => (float) env('FISCAL_TEST_LAB_MAX_INVOICE_TOTAL_VES', 10),
    ],

    'agent' => [
        'max_wait_seconds' => (int) env('FISCAL_AGENT_MAX_WAIT_SECONDS', 15),
        'poll_interval_ms' => (int) env('FISCAL_AGENT_POLL_INTERVAL_MS', 500),
        'offline_after_seconds' => (int) env('FISCAL_AGENT_OFFLINE_AFTER_SECONDS', 120),
    ],

];
