<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="ep-html" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        @include('employee-portal.partials.theme-boot')
        <title>Portal no disponible · Farmadoc</title>
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
                        <h1 class="ep-lead">El portal no está disponible</h1>
                        <p class="ep-text">Tu ficha no está activa. Si crees que es un error, habla con Recursos Humanos.</p>
                        <div class="ep-actions">
                            <a href="{{ route('employee-portal.login') }}" class="ep-btn ep-btn--secondary">Ir al inicio de sesión</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
