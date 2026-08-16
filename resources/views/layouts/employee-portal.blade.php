<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="ep-html" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex,nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('employee-portal.partials.theme-boot')
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Portal">
        <title>{{ filled($title ?? null) ? $title.' · Portal' : 'Portal del empleado' }} · Farmadoc</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logos/favicon.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="ep-body">
        <div class="ep-ambient" aria-hidden="true">
            <div class="ep-orb ep-orb--a"></div>
            <div class="ep-orb ep-orb--b"></div>
            <div class="ep-orb ep-orb--c"></div>
        </div>
        <div class="ep-device">
            {{ $slot }}
        </div>
        @livewireScripts
    </body>
</html>
