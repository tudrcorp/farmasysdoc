<!DOCTYPE html>
<html lang="es" class="sh-html" data-shop-theme="light" style="color-scheme: light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>Sin conexión · Farmadoc</title>
        @include('shop.partials.theme-boot')
        <link rel="icon" type="image/png" href="{{ asset('images/logos/favicon.png') }}">
        @vite(['resources/css/shop.css'])
    </head>

    <body class="sh-body">
        <div class="sh-shell">
            <main class="sh-main" style="padding-top:calc(var(--sh-safe-top) + 2rem);padding-bottom:2rem;">
                <div class="sh-page">
                    <div class="sh-empty" style="padding-top:4rem;">
                        <span class="sh-empty__art" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'alert'])
                        </span>
                        <h3>Sin conexión</h3>
                        <p>No pudimos cargar la tienda. Revisa tu internet y vuelve a intentarlo.</p>

                        <button type="button" class="sh-btn sh-btn--primary" style="margin-top:1.2rem;" onclick="window.location.assign(@js(route('shop.home')))">
                            Reintentar
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
