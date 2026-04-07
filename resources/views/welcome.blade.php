<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Bienvenida</title>
    <meta name="description" content="Portal de bienvenida de FarmaSysDoc para aliados y equipo interno.">
    @vite(['resources/css/app.css'])
    </head>
<body class="relative min-h-screen overflow-x-hidden bg-zinc-100 text-zinc-900 antialiased transition-colors duration-300 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(24,172,178,0.28),transparent_55%)] dark:bg-[radial-gradient(ellipse_120%_80%_at_50%_-20%,rgba(24,172,178,0.18),transparent_55%)]"></div>
        <div class="absolute -left-24 top-10 h-[28rem] w-[28rem] rounded-full bg-cyan-400/35 blur-3xl dark:bg-cyan-500/20"></div>
        <div class="absolute -right-20 top-32 h-[26rem] w-[26rem] rounded-full bg-[#FCE422]/30 blur-3xl dark:bg-[#FCE422]/12"></div>
        <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-teal-500/25 blur-3xl dark:bg-teal-600/15"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_bottom,transparent,rgba(9,9,11,0.04))] dark:bg-[linear-gradient(to_bottom,transparent,rgba(0,0,0,0.35))]"></div>
    </div>

    <div
        class="fixed right-3 top-3 z-30 sm:right-6 sm:top-6"
        role="group"
        aria-label="Seleccionar tema de la interfaz"
    >
        <div class="flex items-center gap-0.5 rounded-[1.35rem] border border-zinc-200/90 bg-white/85 p-1 shadow-lg shadow-zinc-900/10 backdrop-blur-xl dark:border-white/15 dark:bg-zinc-900/80 dark:shadow-black/40">
            <button
                type="button"
                id="theme-light"
                class="theme-seg inline-flex min-w-[5.5rem] items-center justify-center gap-1.5 rounded-[1.1rem] px-3 py-2 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-950"
                aria-pressed="false"
            >
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
                </svg>
                Claro
            </button>
            <button
                type="button"
                id="theme-dark"
                class="theme-seg inline-flex min-w-[5.5rem] items-center justify-center gap-1.5 rounded-[1.1rem] px-3 py-2 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-950"
                aria-pressed="false"
            >
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M21 14.5A8.5 8.5 0 0 1 9.5 3a8.5 8.5 0 1 0 11.5 11.5Z"></path>
                </svg>
                Oscuro
            </button>
        </div>
    </div>

    <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-4 py-16 sm:px-6 lg:px-8">
        <section
            class="relative overflow-hidden rounded-[2rem] border border-white/60 bg-white/75 px-6 py-12 text-center shadow-[0_24px_80px_-12px_rgba(24,172,178,0.25)] ring-1 ring-cyan-500/15 backdrop-blur-2xl dark:border-white/10 dark:bg-zinc-900/55 dark:shadow-[0_24px_80px_-12px_rgba(0,0,0,0.65)] dark:ring-cyan-400/20 sm:px-12 sm:py-14"
        >
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gradient-to-br from-[#FCE422]/40 to-transparent blur-2xl dark:from-[#FCE422]/15"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 h-56 w-56 rounded-full bg-gradient-to-tr from-cyan-400/30 to-transparent blur-3xl dark:from-cyan-500/15"></div>

            <div class="relative mx-auto flex max-w-2xl flex-col items-center">
                <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-800 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-200">
                    Equipo FarmaSysDoc
                </p>

                <a href="{{ route('home') }}" class="logo-hero logo-float inline-flex items-center justify-center drop-shadow-[0_12px_40px_rgba(24,172,178,0.35)] dark:drop-shadow-[0_12px_48px_rgba(24,172,178,0.25)]" aria-label="Inicio FarmaSysDoc">
                    <img src="{{ asset('images/logos/farmadoc-ligth.png') }}" alt="FarmaDoc" class="h-28 w-auto sm:h-36 md:h-40 dark:hidden">
                    <img src="{{ asset('images/logos/farmadoc-dark.png') }}" alt="FarmaDoc" class="hidden h-28 w-auto sm:h-36 md:h-40 dark:block">
                </a>

                <h1 class="mt-8 text-balance text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl md:text-5xl">
                    <span class="bg-gradient-to-r from-cyan-600 via-teal-600 to-cyan-600 bg-clip-text text-transparent dark:from-cyan-300 dark:via-teal-300 dark:to-cyan-200">
                        Salud digital
                    </span>
                    <span class="block text-zinc-800 dark:text-zinc-100">con precision y confianza</span>
                </h1>

                <p class="mt-5 max-w-lg text-pretty text-base font-medium leading-relaxed text-zinc-600 dark:text-zinc-300 sm:text-lg">
                    Nuestra gente, su bienestar.
                </p>

                <div class="mt-10 flex w-full max-w-md flex-col justify-center gap-4 sm:max-w-none sm:flex-row sm:gap-5">
                    <a
                        href="{{ url('/farmaadmin') }}"
                        class="group inline-flex min-h-[3.25rem] flex-1 items-center justify-center rounded-2xl bg-gradient-to-r from-cyan-600 via-teal-600 to-cyan-600 bg-[length:200%_100%] px-8 py-3.5 text-sm font-bold text-white shadow-xl shadow-cyan-600/35 transition-all duration-300 hover:bg-[position:100%_0] hover:shadow-2xl hover:shadow-cyan-500/40 active:scale-[0.98] dark:from-cyan-500 dark:via-teal-500 dark:to-cyan-500 dark:shadow-cyan-900/50"
                    >
                        Entrar a Farmaadmin
                        <svg class="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"></path>
                        </svg>
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                            class="inline-flex min-h-[3.25rem] flex-1 items-center justify-center rounded-2xl border-2 border-cyan-500/40 bg-white/80 px-8 py-3.5 text-sm font-bold text-cyan-900 shadow-md shadow-cyan-500/10 backdrop-blur transition hover:border-cyan-500/70 hover:bg-white active:scale-[0.98] dark:border-cyan-400/35 dark:bg-zinc-900/70 dark:text-cyan-100 dark:hover:bg-zinc-800/90"
                        >
                            Registrar nuevo usuario
                            </a>
                        @endif
                </div>

                <div class="mt-10 flex items-center gap-4">
                    <a
                        href="https://instagram.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-zinc-200/90 bg-white/90 text-zinc-800 shadow-md transition hover:-translate-y-1 hover:border-cyan-400/50 hover:shadow-lg dark:border-white/15 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-cyan-400/40"
                        aria-label="Instagram"
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="17" height="17" rx="5"></rect>
                            <circle cx="12" cy="12" r="4"></circle>
                            <circle cx="17.3" cy="6.7" r="0.8" fill="currentColor" stroke="none"></circle>
                                    </svg>
                                </a>

                    <a
                        href="https://tiktok.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-zinc-200/90 bg-white/90 text-zinc-800 shadow-md transition hover:-translate-y-1 hover:border-cyan-400/50 hover:shadow-lg dark:border-white/15 dark:bg-zinc-900/80 dark:text-zinc-100 dark:hover:border-cyan-400/40"
                        aria-label="TikTok"
                    >
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                            <path d="M14.4 3h2.9c.2 1.6 1.2 3 2.7 3.7v2.8a7.4 7.4 0 0 1-2.7-.5v5.3c0 3.8-3.1 6.7-6.8 6.7A6.8 6.8 0 0 1 3.7 14c0-3.8 3.1-6.8 6.8-6.8.3 0 .6 0 .9.1V10a3.8 3.8 0 0 0-.9-.1A4 4 0 0 0 6.6 14a4 4 0 0 0 3.9 4c2.2 0 3.9-1.8 3.9-4V3Z"></path>
                                    </svg>
                                </a>
                </div>
                </div>
        </section>

        <footer class="mt-8 text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </footer>
            </main>

    <script>
        const root = document.documentElement;
        const btnLight = document.getElementById('theme-light');
        const btnDark = document.getElementById('theme-dark');
        const storedTheme = localStorage.getItem('welcome-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        const segBase =
            'theme-seg inline-flex min-w-[5.5rem] items-center justify-center gap-1.5 rounded-[1.1rem] px-3 py-2 text-xs font-semibold transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-zinc-950';

        const segInactive =
            'text-zinc-500 hover:bg-zinc-100/80 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-zinc-100';

        const segActive =
            'bg-gradient-to-b from-cyan-500/20 to-teal-600/15 text-cyan-950 shadow-inner shadow-cyan-500/20 dark:from-cyan-400/25 dark:to-teal-900/40 dark:text-white dark:shadow-cyan-900/30';

        function syncSegmentUi(theme) {
            const isDark = theme === 'dark';
            btnLight.className = `${segBase} ${isDark ? segInactive : segActive}`;
            btnDark.className = `${segBase} ${isDark ? segActive : segInactive}`;
            btnLight.setAttribute('aria-pressed', (!isDark).toString());
            btnDark.setAttribute('aria-pressed', isDark.toString());
        }

        function applyTheme(theme) {
            const isDark = theme === 'dark';
            root.classList.toggle('dark', isDark);
            syncSegmentUi(theme);
        }

        const initial = storedTheme ?? (prefersDark ? 'dark' : 'light');
        applyTheme(initial);

        btnLight.addEventListener('click', () => {
            localStorage.setItem('welcome-theme', 'light');
            applyTheme('light');
        });

        btnDark.addEventListener('click', () => {
            localStorage.setItem('welcome-theme', 'dark');
            applyTheme('dark');
        });
    </script>

    <style>
        .logo-hero {
            animation: logo-enter 1s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @media (prefers-reduced-motion: reduce) {
            .logo-hero,
            .logo-float {
                animation: none;
            }
        }

        @keyframes logo-enter {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.94);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logo-float {
            animation: logo-enter 1s cubic-bezier(0.22, 1, 0.36, 1) both,
                logo-drift 5s ease-in-out 1.2s infinite;
        }

        @keyframes logo-drift {
            0%,
            100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-6px);
            }
        }
    </style>
    </body>
</html>
