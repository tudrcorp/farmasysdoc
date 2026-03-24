<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>
            (function () {
                try {
                    var mode =
                        localStorage.getItem('theme') ??
                        @json(filament()->getDefaultThemeMode()->value);
                    var dark =
                        mode === 'dark' ||
                        (mode === 'system' &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>
        @include('partials.head')
        <link
            href="https://fonts.bunny.net/css?family=league-spartan:400,500,600,700"
            rel="stylesheet"
        />
    </head>
    <body
        class="farmadoc-auth relative min-h-svh font-sans antialiased"
        style="font-family: 'League Spartan', ui-sans-serif, system-ui, sans-serif"
    >
        @include('filament.farmaadmin.components.simple-auth-ambient')

        <div
            class="relative z-[1] flex min-h-svh flex-col items-center justify-center px-6 py-10"
        >
            <div class="farmadoc-auth-card w-full max-w-md">
                <div class="farmadoc-auth-card-inner flex flex-col gap-6">
                    <a
                        href="{{ route('home') }}"
                        class="flex flex-col items-center gap-2 font-medium"
                        wire:navigate
                    >
                        <img
                            src="{{ asset('images/logos/farmadoc-ligth.png') }}"
                            alt=""
                            class="farmadoc-auth-logo h-[4.6rem] w-auto max-w-full dark:hidden"
                            width="200"
                            height="74"
                        />
                        <img
                            src="{{ asset('images/logos/farmadoc-dark.png') }}"
                            alt=""
                            class="farmadoc-auth-logo hidden h-[4.6rem] w-auto max-w-full dark:block"
                            width="200"
                            height="74"
                        />
                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    <p class="farmadoc-auth-slogan">Nuestra gente, su bienestar.</p>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
