@php
    use App\Support\Shop\ShopCart;
    use App\Support\Shop\ShopTheme;

    $shopCartCount = app(ShopCart::class)->count();
    $hideTabBar = (bool) ($hideTabBar ?? false);
    $shopTheme = ShopTheme::current();
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['sh-html', 'dark' => $shopTheme === 'dark'])
    data-shop-theme="{{ $shopTheme }}"
    style="color-scheme: {{ $shopTheme }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=overlays-content">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ filled($title ?? null) ? $title.' · Farmadoc' : 'Farmadoc' }}</title>
        <meta name="description" content="Pide medicinas, vitaminas y cuidado personal en Farmadoc. Entrega a domicilio o retiro en sucursal.">

        @include('shop.partials.theme-boot')

        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Farmadoc">
        <meta name="application-name" content="Farmadoc">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <link rel="manifest" href="{{ route('shop.manifest') }}">
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/logos/favicon.png') }}">

        @vite(['resources/css/shop.css', 'resources/js/shop.js'])
        @livewireStyles
    </head>

    <body class="sh-body">
        <div
            @class(['sh-shell', 'sh-shell--fixed' => $hideTabBar, 'sh-shell--notabs' => $hideTabBar])
            x-data="shopShell({{ $shopCartCount }})"
            @cart-updated.window="onCartUpdated($event)"
            @shop-toast.window="onToast($event)"
        >
            {{ $slot }}

            @unless ($hideTabBar)
                @include('shop.partials.nav-sheet', ['tab' => $tab ?? ''])
            @endunless

            <div
                class="sh-toast"
                x-show="$store.shop.toast"
                x-cloak
                x-transition:enter="sh-toast-anim"
                x-transition:enter-start="sh-toast-hidden"
                x-transition:leave="sh-toast-anim"
                x-transition:leave-end="sh-toast-hidden"
                role="status"
                aria-live="polite"
            >
                @include('shop.partials.icon', ['icon' => 'check'])
                <span x-text="$store.shop.toast"></span>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
