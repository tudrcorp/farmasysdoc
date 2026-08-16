<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="ep-html" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        @include('employee-portal.partials.theme-boot')
        <title>Sesión cerrada · Portal · Farmadoc</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        @vite(['resources/css/app.css'])
    </head>
    <body class="ep-body">
        <div class="ep-ambient" aria-hidden="true">
            <div class="ep-orb ep-orb--a"></div>
            <div class="ep-orb ep-orb--b"></div>
            <div class="ep-orb ep-orb--c"></div>
        </div>
        <div class="ep-device">
            <div class="ep-app">
                <div class="ep-status">
                    <div>
                        <div class="ep-brand" style="justify-content: center;">
                            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
                            <span>Portal</span>
                        </div>
                        <h1 class="ep-lead">Puedes cerrar esta ventana</h1>
                        <p class="ep-text">Tu sesión del portal terminó. Si necesitas entrar de nuevo, usa el enlace que te envió Recursos Humanos.</p>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
