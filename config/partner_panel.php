<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clave estándar de los usuarios creados por Farmadoc
    |--------------------------------------------------------------------------
    |
    | Los usuarios del panel de aliados (dale · Aliados) que crea un analista de
    | Farmadoc desde la ficha del aliado nacen con esta clave y pueden entrar de
    | inmediato. Cada usuario decide si la cambia (Perfil); mientras la conserve,
    | el panel le muestra un aviso. Cámbiela en producción con la variable de
    | entorno PARTNER_PANEL_DEFAULT_PASSWORD.
    |
    */

    'default_password' => env('PARTNER_PANEL_DEFAULT_PASSWORD', '12345678'),

];
