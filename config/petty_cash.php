<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aprobador de caja chica (WhatsApp)
    |--------------------------------------------------------------------------
    |
    | approver_user_id: usuario de Farmaadmin que recibe el enlace de aprobar/rechazar
    | (teléfono WhatsApp o móvil de entrega en su perfil).
    |
    | approver_whatsapp_phones: CSV opcional que anula approver_user_id si tiene valor.
    |
    */

    'approver_user_id' => env('PETTY_CASH_APPROVER_USER_ID'),

    'approver_whatsapp_phones' => env('PETTY_CASH_APPROVER_WHATSAPP_PHONES', ''),

];
