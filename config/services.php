<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Maps
    |--------------------------------------------------------------------------
    |
    | Servidor: Geocoding + Directions (restringe por IP). No uses esa clave en el navegador.
    |
    | Navegador: obligatoria para el mapa de entrega en Filament (solo Google Maps; restringe por referrer HTTP).
    |
    */
    'google' => [
        'maps_server_api_key' => env('GOOGLE_MAPS_SERVER_API_KEY', env('GOOGLE_API_KEY')),
        'maps_browser_api_key' => env('GOOGLE_MAPS_BROWSER_API_KEY'),
        /** Opcional: IDs de estilo en Google Cloud Console (vector map); claro / oscuro según tema Filament. */
        'maps_map_id_light' => env('GOOGLE_MAPS_MAP_ID_LIGHT'),
        'maps_map_id_dark' => env('GOOGLE_MAPS_MAP_ID_DARK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geocodificación (proxy en /geo/nominatim/*)
    |--------------------------------------------------------------------------
    |
    | Nominatim público aplica límites estrictos (p. ej. 429). El proxy usa Photon
    | como respaldo. Opcional: email de contacto recomendado por la política OSM.
    |
    */
    'nominatim' => [
        'contact_email' => env('NOMINATIM_CONTACT_EMAIL'),
    ],

    'photon' => [
        'url' => env('PHOTON_GEOCODER_URL', 'https://photon.komoot.io'),
    ],

];
