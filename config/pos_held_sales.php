<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ventas en pausa (caja)
    |--------------------------------------------------------------------------
    |
    | Minutos que una venta puede quedar en pausa antes de avisar al cajero
    | (y al gerente de la sucursal). Al cajero se le repite el aviso con la
    | misma frecuencia mientras la venta siga en pausa.
    |
    */

    'reminder_after_minutes' => (int) env('POS_HELD_SALES_REMINDER_MINUTES', 15),

];
