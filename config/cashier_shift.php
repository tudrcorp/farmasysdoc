<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hora diaria de desbloqueo automático (cajeros)
    |--------------------------------------------------------------------------
    |
    | Tras cerrar la caja física, el cajero no puede ingresar hasta la próxima
    | ocurrencia de esta hora (zona horaria de la aplicación), salvo override
    | manual de un administrador.
    |
    */

    'daily_unlock_hour' => (int) env('CASHIER_SHIFT_UNLOCK_HOUR', 6),

    'daily_unlock_minute' => (int) env('CASHIER_SHIFT_UNLOCK_MINUTE', 0),

];
