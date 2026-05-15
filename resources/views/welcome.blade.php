@php
    $seoBaseUrl = rtrim((string) config('app.url', 'https://farmasysdoc.farmadoc.net'), '/');
    $seoCanonical = $seoBaseUrl.'/';
    $seoTitle = 'FarmaSysDoc | Gestion farmaceutica, ventas e inventario';
    $seoDescription = 'FarmaSysDoc de Farmadoc: plataforma de gestion farmaceutica para ventas, inventario, compras, caja y reportes operativos.';
    $seoImage = asset('images/logos/favicon.png');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" />
        <meta name="googlebot" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" />
        <meta name="author" content="Farmadoc" />
        <meta name="theme-color" content="#18ACB2" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />
        <title>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        <link rel="canonical" href="{{ $seoCanonical }}" />
        <link rel="alternate" hreflang="es-VE" href="{{ $seoCanonical }}" />
        <link rel="alternate" hreflang="x-default" href="{{ $seoCanonical }}" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="{{ config('app.name') }}" />
        <meta property="og:locale" content="es_VE" />
        <meta property="og:title" content="{{ $seoTitle }}" />
        <meta property="og:description" content="{{ $seoDescription }}" />
        <meta property="og:url" content="{{ $seoCanonical }}" />
        <meta property="og:image" content="{{ $seoImage }}" />
        <meta property="og:image:type" content="image/png" />
        <meta property="og:image:width" content="1024" />
        <meta property="og:image:height" content="1024" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $seoTitle }}" />
        <meta name="twitter:description" content="{{ $seoDescription }}" />
        <meta name="twitter:image" content="{{ $seoImage }}" />
        <link rel="icon" type="image/png" sizes="1024x1024" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="apple-touch-icon" sizes="1024x1024" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "Organization",
                "name": "Farmadoc",
                "url": "{{ $seoBaseUrl }}",
                "logo": "{{ $seoImage }}",
                "sameAs": [
                    "https://instagram.com",
                    "https://tiktok.com"
                ]
            }
        </script>
        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "WebSite",
                "name": "{{ config('app.name') }}",
                "url": "{{ $seoBaseUrl }}"
            }
        </script>
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

    <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-12 px-4 py-16 sm:px-6 lg:px-8">
        <section class="scroll-reveal relative overflow-hidden rounded-[2rem] border border-white/60 bg-white/75 px-6 py-12 text-center shadow-[0_24px_80px_-12px_rgba(24,172,178,0.25)] ring-1 ring-cyan-500/15 backdrop-blur-2xl dark:border-white/10 dark:bg-zinc-900/55 dark:shadow-[0_24px_80px_-12px_rgba(0,0,0,0.65)] dark:ring-cyan-400/20 sm:px-12 sm:py-14">
            <div class="parallax-element pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gradient-to-br from-[#FCE422]/40 to-transparent blur-2xl dark:from-[#FCE422]/15" data-parallax-speed="0.12"></div>
            <div class="parallax-element pointer-events-none absolute -bottom-20 -left-10 h-56 w-56 rounded-full bg-gradient-to-tr from-cyan-400/30 to-transparent blur-3xl dark:from-cyan-500/15" data-parallax-speed="0.18"></div>

            <div class="relative mx-auto flex max-w-4xl flex-col items-center">
                <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.2em] text-cyan-800 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-200">
                    Equipo FarmaSysDoc
                </p>

                <a href="{{ route('home') }}" class="logo-hero logo-float inline-flex items-center justify-center drop-shadow-[0_12px_40px_rgba(24,172,178,0.35)] dark:drop-shadow-[0_12px_48px_rgba(24,172,178,0.25)]" aria-label="Inicio FarmaSysDoc">
                    <img src="{{ asset('images/logos/farmadoc-ligth.png') }}" alt="FarmaDoc" class="h-28 w-auto sm:h-36 md:h-40 dark:hidden">
                    <img src="{{ asset('images/logos/farmadoc-dark.png') }}" alt="FarmaDoc" class="hidden h-28 w-auto sm:h-36 md:h-40 dark:block">
                </a>

                <h1 class="mt-8 text-balance text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl md:text-5xl">
                    <span class="bg-gradient-to-r from-cyan-600 via-teal-600 to-cyan-600 bg-clip-text text-transparent dark:from-cyan-300 dark:via-teal-300 dark:to-cyan-200">
                        Encuentra productos de forma rapida y sencilla
                    </span>
                </h1>

                <p class="mt-5 max-w-2xl text-pretty text-base font-medium leading-relaxed text-zinc-600 dark:text-zinc-300 sm:text-lg">
                    Busca por nombre, o principio activo para ubicar el producto exacto en segundos.
                </p>

                <div class="mt-8 w-full max-w-3xl">
                    <label for="public-product-search" class="sr-only">Buscar productos</label>
                    <div class="flex items-center gap-2 rounded-2xl border border-cyan-400/25 bg-white/90 p-2 shadow-lg shadow-cyan-900/10 dark:border-cyan-400/20 dark:bg-zinc-900/80">
                        <svg class="h-5 w-5 shrink-0 text-cyan-600 dark:text-cyan-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.5-3.5"></path>
                        </svg>
                        <input
                            id="public-product-search"
                            type="search"
                            class="w-full bg-transparent py-2.5 text-sm font-medium text-zinc-900 outline-none placeholder:text-zinc-500 dark:text-zinc-100 dark:placeholder:text-zinc-400"
                            placeholder="Buscar: acetaminofen, 7598484000027, ibuprofeno..."
                            autocomplete="off"
                            spellcheck="false"
                            data-search-endpoint="{{ route('public.products.search') }}"
                        >
                        <span id="search-status" class="hidden text-xs font-semibold text-cyan-700 dark:text-cyan-300">Buscando...</span>
                    </div>
                    <div id="public-search-feedback" class="mt-2 text-left text-xs text-zinc-500 dark:text-zinc-400">Escriba al menos 2 caracteres.</div>
                    <div id="public-search-results" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"></div>
                </div>

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
                    <a
                        href="{{ route('public.api-docs') }}"
                        class="inline-flex min-h-[3.25rem] flex-1 items-center justify-center rounded-2xl border-2 border-cyan-500/40 bg-white/80 px-8 py-3.5 text-sm font-bold text-cyan-900 shadow-md shadow-cyan-500/10 backdrop-blur transition hover:border-cyan-500/70 hover:bg-white active:scale-[0.98] dark:border-cyan-400/35 dark:bg-zinc-900/70 dark:text-cyan-100 dark:hover:bg-zinc-800/90"
                    >
                        Ver documentacion API
                    </a>
                </div>
            </div>
        </section>

        <section class="scroll-reveal rounded-3xl border border-white/60 bg-white/70 p-6 shadow-xl shadow-cyan-900/10 backdrop-blur-xl dark:border-white/10 dark:bg-zinc-900/60 sm:p-8">
            <div class="grid gap-5 md:grid-cols-3">
                <article class="rounded-2xl border border-zinc-200/70 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/70">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">Busqueda inteligente</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Prioriza coincidencias exactas por codigo y resultados relevantes por nombre, marca y principio activo.</p>
                </article>
                <article class="rounded-2xl border border-zinc-200/70 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/70">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">Velocidad de consulta</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Respuesta en tiempo real con debounce y cancelacion de solicitudes para una experiencia fluida.</p>
                </article>
                <article class="rounded-2xl border border-zinc-200/70 bg-white/80 p-5 dark:border-white/10 dark:bg-zinc-900/70">
                    <h2 class="text-base font-bold text-zinc-900 dark:text-white">Disponibilidad visible</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Muestra stock disponible y datos clave para decidir rapido en mostrador o canal digital.</p>
                </article>
            </div>
        </section>

        <section class="scroll-reveal relative overflow-hidden rounded-3xl border border-cyan-500/25 bg-gradient-to-r from-cyan-600/95 via-teal-600/95 to-cyan-700/95 p-8 text-white shadow-2xl shadow-cyan-900/25 sm:p-10">
            <div class="absolute -right-16 -top-10 h-44 w-44 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -left-10 bottom-0 h-40 w-40 rounded-full bg-[#FCE422]/25 blur-3xl"></div>
            <div class="relative grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="inline-flex rounded-full border border-white/40 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-cyan-50">Aliados comerciales</p>
                    <h2 class="mt-4 text-2xl font-bold leading-tight sm:text-3xl">Invitamos a empresas y marcas a formar parte de nuestros aliados comerciales.</h2>
                    <p class="mt-3 max-w-2xl text-sm text-cyan-50/95 sm:text-base">Conecta tu empresa con la red Farmadoc y amplifica tus operaciones de inventario, compras, ventas y trazabilidad en una sola plataforma.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                    <a href="{{ Route::has('register') ? route('register') : route('public.api-docs') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-6 py-3 text-sm font-bold text-cyan-800 transition hover:bg-cyan-50">Quiero ser aliado comercial</a>
                    <a href="{{ route('public.api-docs') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/60 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">Integrar por API</a>
                </div>
            </div>
        </section>

        <footer class="scroll-reveal mt-2 border-t border-zinc-200/70 pt-6 text-xs text-zinc-500 dark:border-white/10 dark:text-zinc-400">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} {{ config('app.name') }} · Minimal, rapido y enfocado en resultados.</p>
                <nav class="flex items-center gap-4">
                    <a href="{{ route('sitemap') }}" class="transition hover:text-cyan-600 dark:hover:text-cyan-300">Sitemap</a>
                    <a href="{{ route('public.api-docs') }}" class="transition hover:text-cyan-600 dark:hover:text-cyan-300">API</a>
                    <a href="{{ url('/farmaadmin') }}" class="transition hover:text-cyan-600 dark:hover:text-cyan-300">Farmaadmin</a>
                </nav>
            </div>
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

        const revealNodes = document.querySelectorAll('.scroll-reveal');
        if ('IntersectionObserver' in window && revealNodes.length > 0) {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.16 });

            revealNodes.forEach((node) => revealObserver.observe(node));
        } else {
            revealNodes.forEach((node) => node.classList.add('is-visible'));
        }

        const parallaxNodes = document.querySelectorAll('.parallax-element');
        if (parallaxNodes.length > 0) {
            const updateParallax = () => {
                const offsetY = window.scrollY || 0;
                parallaxNodes.forEach((node) => {
                    const speed = Number(node.dataset.parallaxSpeed || 0.1);
                    node.style.transform = `translate3d(0, ${offsetY * speed}px, 0)`;
                });
            };

            updateParallax();
            window.addEventListener('scroll', updateParallax, { passive: true });
        }

        const searchInput = document.getElementById('public-product-search');
        const resultsContainer = document.getElementById('public-search-results');
        const feedbackNode = document.getElementById('public-search-feedback');
        const statusNode = document.getElementById('search-status');

        if (searchInput && resultsContainer && feedbackNode && statusNode) {
            let debounceTimer = null;
            let abortController = null;

            const escapeHtml = (text) => String(text)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatUsd = (amount) => {
                const value = Number(amount || 0);
                return value.toLocaleString('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            };

            const formatStock = (amount) => {
                const value = Number(amount || 0);
                return value <= 0 ? 'Sin stock disponible' : `Stock disponible: ${value.toLocaleString('es-VE', { minimumFractionDigits: 0, maximumFractionDigits: 3 })}`;
            };

            const normalizeIngredient = (value) => {
                const text = String(value || '').trim();
                if (text === '' || text === '[]') {
                    return 'No especificado';
                }

                if (text.startsWith('[') && text.endsWith(']')) {
                    const compact = text.slice(1, -1).replaceAll('"', '').trim();
                    return compact === '' ? 'No especificado' : compact;
                }

                return text;
            };

            const setFeedback = (message) => {
                feedbackNode.textContent = message;
            };

            const noResultsCtaCard = () => {
                return `
                    <article class="col-span-full rounded-3xl border border-cyan-500/25 bg-white/90 p-5 shadow-[0_18px_42px_-22px_rgba(8,145,178,0.42)] backdrop-blur-xl dark:border-cyan-400/25 dark:bg-zinc-900/80">
                        <h3 class="text-sm font-extrabold uppercase tracking-wide text-zinc-900 dark:text-zinc-100">No encontramos ese medicamento en inventario</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">
                            Podemos gestionarlo como pedido personalizado nacional o internacional. Escríbenos directo y te ayudamos de inmediato.
                        </p>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <a
                                href="https://wa.me/584127018390"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center rounded-2xl bg-emerald-500 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-emerald-600"
                            >
                                WhatsApp 04127018390
                            </a>
                            <a
                                href="mailto:pedidos@farmadoc.net"
                                class="inline-flex items-center justify-center rounded-2xl border border-cyan-500/35 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-cyan-700 transition hover:bg-cyan-500/10 dark:text-cyan-300"
                            >
                                pedidos@farmadoc.net
                            </a>
                        </div>
                    </article>
                `;
            };

            const renderResults = (items) => {
                if (!Array.isArray(items) || items.length === 0) {
                    resultsContainer.innerHTML = noResultsCtaCard();
                    setFeedback('No hay productos con existencia mayor a 1 para esa búsqueda.');
                    return;
                }

                resultsContainer.innerHTML = items.map((item) => {
                    const hasVat = item.applies_vat ? '<span class="inline-flex items-center rounded-full border border-cyan-500/25 bg-cyan-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-cyan-700 dark:border-cyan-400/30 dark:bg-cyan-500/15 dark:text-cyan-200">IVA</span>' : '';
                    const stockValue = Number(item.stock_available || 0);
                    const stockClass = stockValue > 0
                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/25 dark:bg-emerald-500/15 dark:text-emerald-200'
                        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/15 dark:text-rose-200';
                    const ingredientText = normalizeIngredient(item.active_ingredient);

                    return `
                        <article class="ios-product-card group rounded-3xl border border-white/60 bg-white/85 p-4 shadow-[0_14px_40px_-18px_rgba(15,23,42,0.35)] ring-1 ring-zinc-950/5 transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_55px_-20px_rgba(8,145,178,0.45)] dark:border-white/10 dark:bg-zinc-900/75 dark:ring-white/10">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="line-clamp-2 text-sm font-extrabold uppercase tracking-[0.02em] text-zinc-900 dark:text-zinc-100">${escapeHtml(item.name || 'Producto')}</h3>
                                ${hasVat}
                            </div>

                            <div class="mt-3 space-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                <p class="truncate"><span class="font-semibold text-zinc-700 dark:text-zinc-300">Codigo:</span> ${escapeHtml(item.barcode || '—')}</p>
                                <p class="truncate"><span class="font-semibold text-zinc-700 dark:text-zinc-300">Marca:</span> ${escapeHtml(item.brand || '—')}</p>
                                <p class="line-clamp-2"><span class="font-semibold text-zinc-700 dark:text-zinc-300">Principio activo:</span> ${escapeHtml(ingredientText)}</p>
                            </div>

                            <div class="mt-4 flex items-end justify-between gap-3">
                                <p class="text-lg font-black tracking-tight text-cyan-700 dark:text-cyan-300">${formatUsd(item.sale_price)}</p>
                                <p class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold ${stockClass}">${escapeHtml(formatStock(stockValue))}</p>
                            </div>
                        </article>
                    `;
                }).join('');

                setFeedback(`Mostrando ${items.length} resultado(s).`);
            };

            const runSearch = async (query) => {
                const endpoint = searchInput.dataset.searchEndpoint;
                if (!endpoint) {
                    return;
                }

                if (abortController) {
                    abortController.abort();
                }

                abortController = new AbortController();
                statusNode.classList.remove('hidden');
                setFeedback('Buscando productos...');

                try {
                    const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                        },
                        signal: abortController.signal,
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo completar la busqueda.');
                    }

                    const payload = await response.json();
                    renderResults(payload.data || []);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    resultsContainer.innerHTML = '';
                    setFeedback('Tuvimos un problema al buscar. Intente de nuevo en unos segundos.');
                } finally {
                    statusNode.classList.add('hidden');
                }
            };

            searchInput.addEventListener('input', (event) => {
                const query = event.target.value.trim();

                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }

                if (query.length < 2) {
                    if (abortController) {
                        abortController.abort();
                    }
                    resultsContainer.innerHTML = '';
                    setFeedback('Escriba al menos 2 caracteres.');
                    statusNode.classList.add('hidden');
                    return;
                }

                debounceTimer = setTimeout(() => {
                    runSearch(query);
                }, 220);
            });
        }
    </script>

    <style>
        .logo-hero {
            animation: logo-enter 1s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .scroll-reveal {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 0.6s ease, transform 0.6s ease;
            will-change: transform, opacity;
        }

        .scroll-reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        @@media (prefers-reduced-motion: reduce) {
            .logo-hero,
            .logo-float,
            .scroll-reveal {
                animation: none;
                transition: none;
                transform: none;
                opacity: 1;
            }
        }

        @@keyframes logo-enter {
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

        .ios-product-card {
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .ios-product-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                140deg,
                rgba(255, 255, 255, 0.38) 0%,
                rgba(255, 255, 255, 0) 45%
            );
            pointer-events: none;
        }

        @@keyframes logo-drift {
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
