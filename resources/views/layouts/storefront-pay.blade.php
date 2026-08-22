<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0E949A">
        <title>{{ filled($title ?? null) ? $title.' · Farmadoc' : 'Pago seguro · Farmadoc' }}</title>
        <meta name="description" content="Zona segura de pago Farmadoc. Elige entrega o retiro y paga con pago móvil o transferencia.">
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        <script>
            (function () {
                var stored = localStorage.getItem('welcome-theme');
                var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="fd-storefront fd-pay-body antialiased">
        {{ $slot }}
        @livewireScripts
    </body>
</html>
