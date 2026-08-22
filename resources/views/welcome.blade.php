@php
    $seoBaseUrl = rtrim((string) config('app.url', 'https://farmasysdoc.farmadoc.net'), '/');
    $seoCanonical = $seoBaseUrl.'/';
    $seoTitle = 'Farmadoc | Farmacia en línea, medicinas y entrega';
    $seoDescription = 'Compra medicinas, vitaminas y productos de salud en Farmadoc. Entrega rápida, productos originales y atención farmacéutica en Venezuela.';
    $seoImage = asset('images/logos/favicon.png');
    $categories = is_array($categories ?? null) ? $categories : [];
    $bestsellers = is_array($bestsellers ?? null) ? $bestsellers : [];
    $offers = is_array($offers ?? null) ? $offers : [];
    $offersEyebrow = $offersEyebrow ?? 'Para ti';
    $offersTitle = $offersTitle ?? 'Recomendados para ti';
    $whatsappUrl = $whatsappUrl ?? 'https://wa.me/584127018390';
    $whatsappDisplay = $whatsappDisplay ?? '0412-701-8390';
    $ordersEmail = $ordersEmail ?? 'pedidos@farmadoc.net';
    $storefrontBoot = is_array($storefrontBoot ?? null) ? $storefrontBoot : [
        'searchEndpoint' => route('public.products.search'),
        'checkoutEndpoint' => route('storefront.checkout'),
        'whatsappUrl' => $whatsappUrl,
        'ordersEmail' => $ordersEmail,
    ];
    $fallbackCategories = [
        ['name' => 'Medicinas', 'slug' => 'medicinas', 'image_url' => null, 'product_count' => 0, 'is_medication' => true],
        ['name' => 'Salud', 'slug' => 'salud', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
        ['name' => 'Cuidado personal', 'slug' => 'cuidado-personal', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
        ['name' => 'Dispositivos', 'slug' => 'dispositivos', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
        ['name' => 'Vitaminas', 'slug' => 'vitaminas', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
        ['name' => 'Mamá y bebé', 'slug' => 'mama-bebe', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
        ['name' => 'Bienestar', 'slug' => 'bienestar', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
        ['name' => 'Ofertas', 'slug' => 'ofertas', 'image_url' => null, 'product_count' => 0, 'is_medication' => false],
    ];
    $shopCategories = $categories !== [] ? $categories : $fallbackCategories;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
        <meta name="author" content="Farmadoc">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#18ACB2">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <title>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        <link rel="canonical" href="{{ $seoCanonical }}">
        <link rel="alternate" hreflang="es-VE" href="{{ $seoCanonical }}">
        <link rel="alternate" hreflang="x-default" href="{{ $seoCanonical }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:locale" content="es_VE">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoCanonical }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <link rel="icon" type="image/png" sizes="1024x1024" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="apple-touch-icon" sizes="1024x1024" href="{{ asset('images/logos/favicon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "Pharmacy",
                "name": "Farmadoc",
                "url": "{{ $seoBaseUrl }}",
                "logo": "{{ $seoImage }}",
                "telephone": "+584127018390"
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
        <script>
            (function () {
                var stored = localStorage.getItem('welcome-theme');
                var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/storefront.js'])
    </head>
    <body class="fd-storefront antialiased">
        <script type="application/json" id="storefront-boot">@json($storefrontBoot)</script>

        <header class="fd-storefront-header" data-storefront-header>
            <div class="fd-storefront-header__bar">
                <a href="{{ route('home') }}" class="fd-logo" aria-label="Inicio Farmadoc">
                    <img src="{{ asset('images/logos/farmadoc-ligth.png') }}" alt="Farmadoc" class="dark:hidden">
                    <img src="{{ asset('images/logos/farmadoc-dark.png') }}" alt="Farmadoc" class="hidden dark:block">
                </a>

                <div class="fd-search">
                    <label class="sr-only" for="storefront-search">Buscar productos</label>
                    <div class="fd-search__field">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input
                            id="storefront-search"
                            type="search"
                            placeholder="Buscar medicinas, vitaminas, marcas..."
                            autocomplete="off"
                            spellcheck="false"
                            data-storefront-search
                            data-search-endpoint="{{ route('public.products.search') }}"
                        >
                        <button type="button" class="fd-search__submit" aria-label="Buscar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="fd-search__dropdown fd-glass" data-search-dropdown></div>
                </div>

                <div class="fd-header-utils">
                    {{-- El selector de tema vive ahora en el menú inferior --}}
                    <div class="fd-theme-seg fd-glass fd-theme-seg--desktop" role="group" aria-label="Tema">
                        <button type="button" data-theme="light">Claro</button>
                        <button type="button" data-theme="dark">Oscuro</button>
                    </div>
                    <button type="button" class="fd-header-link" data-open-modal="track">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>
                        Rastrear
                    </button>
                    <a class="fd-header-link" href="{{ url('/farmaadmin') }}">Entrar</a>
                    <button type="button" class="fd-icon-btn fd-cart-btn" data-cart-button aria-label="Abrir carrito">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M6 6 5 3H2"/></svg>
                        <span class="fd-cart-badge" data-cart-count>0</span>
                    </button>
                    <button type="button" class="fd-icon-btn fd-burger" data-open-nav aria-label="Abrir menú">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                </div>
            </div>

            <nav class="fd-nav" aria-label="Categorías">
                <a href="#inicio" class="is-active">Inicio</a>
                <a href="#categorias">Categorías</a>
                <a href="#mas-vendidos">Medicinas</a>
                <a href="#ofertas">Ofertas</a>
                <a href="#receta">Recetas <span class="fd-nav__new">Nuevo</span></a>
                <a href="#servicios">Servicios</a>
            </nav>
        </header>

        <main class="fd-shell">
            <section id="inicio" class="fd-hero fd-glass fd-reveal">
                <div class="fd-hero__copy">
                    <p class="fd-kicker">Farmacia Farmadoc</p>
                    <h1>Tu salud, nuestra prioridad.</h1>
                    <p class="fd-hero__lead">
                        Medicinas originales, vitaminas y cuidado personal con entrega rápida. Busca, arma tu carrito y pide en segundos.
                    </p>
                    <div class="fd-hero__perks">
                        <div class="fd-perk">
                            <span class="fd-perk__icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 5 5L20 7"/></svg>
                            </span>
                            Originales
                        </div>
                        <div class="fd-perk">
                            <span class="fd-perk__icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/></svg>
                            </span>
                            Entrega rápida
                        </div>
                        <div class="fd-perk">
                            <span class="fd-perk__icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3"/><path d="M5 19a7 7 0 0 1 14 0"/></svg>
                            </span>
                            Asesoría experta
                        </div>
                    </div>
                    <div class="fd-hero__cta">
                        <a class="fd-btn fd-btn--primary" href="#mas-vendidos">
                            Comprar medicinas
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <button type="button" class="fd-btn fd-btn--ghost" data-open-modal="recipe">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16M4 12h16"/></svg>
                            Subir receta
                        </button>
                    </div>
                    <div class="fd-social-proof">
                        <div class="fd-avatars" aria-hidden="true">
                            <span>MG</span><span>AR</span><span>LC</span><span>JD</span>
                        </div>
                        <div>
                            <strong>4.8 ★</strong>
                            <div class="fd-product-card__meta">Clientes que ya confían en Farmadoc</div>
                        </div>
                    </div>
                </div>

                <div class="fd-stage" aria-hidden="true">
                    <div class="fd-stage__glow"></div>
                    <div class="fd-stage__ring"></div>
                    <div class="fd-stage__ring fd-stage__ring--2"></div>
                    <div class="fd-stage__platform"></div>
                    <img
                        class="fd-stage__photo"
                        src="{{ asset('images/storefront/hero-medicamentos.jpg') }}"
                        alt=""
                        width="960"
                        height="720"
                    >
                </div>
            </section>

            <section class="fd-trust fd-scroll-reveal" aria-label="Beneficios">
                <article class="fd-trust__item fd-glass">
                    <span class="fd-trust__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <rect x="2.5" y="12" width="16.5" height="10.5" rx="2.2" fill="#0E949A"/>
                            <path d="M19 14.5h5.2L27.5 19v3.5H19v-8z" fill="#18ACB2"/>
                            <rect x="6.2" y="7.2" width="8.2" height="6.2" rx="1.3" fill="#FCE422"/>
                            <path d="M8.4 9.2h3.8" stroke="#0E949A" stroke-width="1.3" stroke-linecap="round"/>
                            <circle cx="9.2" cy="23.8" r="2.35" fill="#10282C"/>
                            <circle cx="9.2" cy="23.8" r="1.05" fill="#E7F7F8"/>
                            <circle cx="23.4" cy="23.8" r="2.35" fill="#10282C"/>
                            <circle cx="23.4" cy="23.8" r="1.05" fill="#E7F7F8"/>
                            <path d="M28.2 8.2 26 10.4l2.2 2.2" stroke="#FCE422" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div><strong>Entrega express</strong><span>A tu puerta, cuando lo necesitas</span></div>
                </article>
                <article class="fd-trust__item fd-glass">
                    <span class="fd-trust__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <rect x="3.2" y="8" width="20.5" height="14.5" rx="2.6" fill="#0E949A"/>
                            <rect x="3.2" y="11.6" width="20.5" height="3.4" fill="#18ACB2"/>
                            <rect x="6.2" y="17.6" width="6.4" height="2" rx="1" fill="#E7F7F8"/>
                            <path d="M23.2 15.2 28 17.1v5.1c0 3.3-2.4 5.5-4.8 6.2-2.4-.7-4.8-2.9-4.8-6.2v-5.1l4.8-1.9z" fill="#FCE422"/>
                            <path d="m21.3 21.4 1.7 1.7 3.2-3.3" stroke="#0E949A" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div><strong>Pagos seguros</strong><span>USD, Bs. y transferencias</span></div>
                </article>
                <article class="fd-trust__item fd-glass">
                    <span class="fd-trust__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path d="M8.2 11.2A8.6 8.6 0 0 1 22.8 8.4" stroke="#0E949A" stroke-width="2.3" stroke-linecap="round"/>
                            <path d="M22.4 4.8v4.4h-4.3" stroke="#0E949A" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M23.8 20.8A8.6 8.6 0 0 1 9.2 23.6" stroke="#18ACB2" stroke-width="2.3" stroke-linecap="round"/>
                            <path d="M9.6 27.2v-4.4h4.3" stroke="#18ACB2" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="11.4" y="11.6" width="9.2" height="8.8" rx="2" fill="#FCE422"/>
                            <path d="M13.6 16h4.8M16 13.6v4.8" stroke="#0E949A" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <div><strong>Cambios fáciles</strong><span>Te acompañamos después de comprar</span></div>
                </article>
                <article class="fd-trust__item fd-glass">
                    <span class="fd-trust__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                            <path d="M7 14.2c0-5.2 4.3-8.7 9.4-8.7S25.8 9 25.8 14.2 21.5 23 16.4 23c-1.2 0-2.4-.2-3.4-.6L6.8 25.6l1.6-4.2A8.6 8.6 0 0 1 7 14.2Z" fill="#0E949A"/>
                            <circle cx="12.2" cy="14.2" r="1.15" fill="#E7F7F8"/>
                            <circle cx="16.4" cy="14.2" r="1.15" fill="#E7F7F8"/>
                            <circle cx="20.6" cy="14.2" r="1.15" fill="#E7F7F8"/>
                            <circle cx="24.4" cy="24.2" r="5.3" fill="#FCE422"/>
                            <path d="M24.4 21.4v3.1l2.1 1.2" stroke="#0E949A" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div><strong>Atención 24/7</strong><span>WhatsApp {{ $whatsappDisplay }}</span></div>
                </article>
            </section>

            <section id="categorias" class="fd-section fd-scroll-reveal">
                <div class="fd-section__head">
                    <div>
                        <p class="fd-kicker">Catálogo</p>
                        <h2>Compra por categoría</h2>
                    </div>
                    <p>Toca una categoría y la buscamos al instante.</p>
                </div>
                <div class="fd-cats">
                    @foreach ($shopCategories as $category)
                        <x-storefront.category-card :category="$category" />
                    @endforeach
                </div>
            </section>

            <section id="mas-vendidos" class="fd-section fd-scroll-reveal">
                <div class="fd-section__head">
                    <div>
                        <p class="fd-kicker">Destacados</p>
                        <h2>Más vendidos</h2>
                    </div>
                    <a class="fd-header-link" href="#ofertas">Ver ofertas</a>
                </div>
                @if ($bestsellers !== [])
                    <div class="fd-carousel" data-carousel>
                        <x-storefront.carousel-controls />
                        <div class="fd-carousel__track" data-carousel-track>
                            @foreach ($bestsellers as $product)
                                <x-storefront.product-card :product="$product" />
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="fd-empty fd-glass">Aún estamos cargando el catálogo público. Usa el buscador para consultar inventario.</div>
                @endif
            </section>

            <section id="ofertas" class="fd-section fd-scroll-reveal">
                <div class="fd-section__head">
                    <div>
                        <p class="fd-kicker">{{ $offersEyebrow }}</p>
                        <h2>{{ $offersTitle }}</h2>
                    </div>
                </div>
                @if ($offers !== [])
                    <div class="fd-carousel" data-carousel>
                        <x-storefront.carousel-controls />
                        <div class="fd-carousel__track" data-carousel-track>
                            @foreach ($offers as $product)
                                <x-storefront.product-card :product="$product" />
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="fd-empty fd-glass">No hay descuentos activos en este momento. Explora los más vendidos o busca tu medicamento.</div>
                @endif
            </section>

            <section id="receta" class="fd-rx fd-scroll-reveal">
                <div class="fd-rx__grid">
                    <svg width="72" height="72" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="5" fill="#FCE422"/>
                        <path d="M12 7v10M7 12h10" stroke="#0E949A" stroke-width="2.4"/>
                    </svg>
                    <div>
                        <p class="fd-kicker" style="background:rgb(255 255 255 / .12);color:#fff;border-color:rgb(255 255 255 / .2)">Receta médica</p>
                        <h2 style="margin:.45rem 0 0;font-size:1.7rem;letter-spacing:-.03em;">¿Tienes una receta? La cotizamos al instante.</h2>
                        <p style="margin:.4rem 0 0;opacity:.9;">Súbela o escríbenos por WhatsApp y un farmacéutico te confirma disponibilidad y precio.</p>
                    </div>
                    <button type="button" class="fd-btn fd-btn--yellow" data-open-modal="recipe">Subir ahora</button>
                </div>
            </section>

            <section class="fd-stats fd-scroll-reveal" aria-label="Confianza Farmadoc">
                <div class="fd-section__head">
                    <div>
                        <p class="fd-kicker">Confianza</p>
                        <h2>Por qué comprar en Farmadoc</h2>
                    </div>
                    <p>Farmacia licenciada, inventario real y entregas que se pueden rastrear.</p>
                </div>

                <div class="fd-stats__grid">
                    <article class="fd-stats__card fd-glass">
                        <span class="fd-stats__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3 5 6v6c0 5 3.2 8.4 7 9 3.8-.6 7-4 7-9V6l-7-3z"/><path d="m8.8 12.1 2.2 2.2 4.4-4.6"/></svg>
                        </span>
                        <strong>Farmacia licenciada</strong>
                        <span>Operación regulada y trazable ante SENIAT y el Ministerio de Salud.</span>
                    </article>
                    <article class="fd-stats__card fd-glass">
                        <span class="fd-stats__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.3"/></svg>
                        </span>
                        <strong>Cobertura nacional</strong>
                        <span>Despacho a todo el país, con seguimiento hasta tu puerta.</span>
                    </article>
                    <article class="fd-stats__card fd-glass">
                        <span class="fd-stats__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="8.2"/><path d="M12 8v4.2l2.6 1.6"/></svg>
                        </span>
                        <p class="fd-stats__metric">90%</p>
                        <strong>A tiempo</strong>
                        <span>Pedidos entregados dentro de la ventana prometida.</span>
                    </article>
                    <article class="fd-stats__card fd-glass">
                        <span class="fd-stats__icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3v3M12 18v3M4.9 6.2l2.1 2.1M17 15.7l2.1 2.1M3 12h3M18 12h3M4.9 17.8 7 15.7M17 8.3l2.1-2.1"/><circle cx="12" cy="12" r="3.2"/></svg>
                        </span>
                        <p class="fd-stats__metric">100%</p>
                        <strong>Genuinos</strong>
                        <span>Cadena de frío e inventario real, sin productos de origen dudoso.</span>
                    </article>
                </div>
            </section>

            <section id="servicios" class="fd-cta-grid fd-scroll-reveal">
                <article class="fd-cta-card fd-glass">
                    <div style="position:absolute;inset:0;background:linear-gradient(135deg,#0E949A,#163a3e);"></div>
                    <h3>Habla con un farmacéutico</h3>
                    <p>Dudas de dosis, genéricos o interacciones. Te orientamos de verdad.</p>
                    <a class="fd-btn fd-btn--yellow" href="{{ $whatsappUrl }}" target="_blank" rel="noopener" style="margin-top:1rem;align-self:flex-start;">Consultar ahora</a>
                </article>
                <article class="fd-cta-card fd-glass">
                    <div style="position:absolute;inset:0;background:linear-gradient(135deg,#12686d,#18ACB2);"></div>
                    <h3>Delivery a domicilio</h3>
                    <p>Arma el carrito y realiza el pago. Te confirmamos en minutos.</p>
                    <button type="button" class="fd-btn fd-btn--yellow" data-cart-button style="margin-top:1rem;align-self:flex-start;">Ver mi carrito</button>
                </article>
            </section>

            <footer class="fd-footer">
                <p>&copy; {{ now()->year }} {{ config('app.name') }} · Farmadoc. Salud, cerca de ti.</p>
                <nav>
                    <a href="{{ route('sitemap') }}">Sitemap</a>
                    <a href="{{ route('public.api-docs') }}">API</a>
                    <a href="{{ url('/farmaadmin') }}">Farmaadmin</a>
                    <a href="mailto:{{ $ordersEmail }}">{{ $ordersEmail }}</a>
                </nav>
            </footer>
        </main>

        <div class="fd-mobile-bar fd-glass">
            <span>Carrito · <strong data-cart-total>$0.00</strong></span>
            <button type="button" class="fd-btn fd-btn--primary" data-cart-button style="min-height:2.6rem;">Abrir</button>
        </div>

        <div class="fd-overlay" data-overlay></div>

        <aside class="fd-drawer fd-glass" data-cart-drawer aria-label="Carrito de compra" aria-hidden="true">
            <div class="fd-drawer__head">
                <div>
                    <p class="fd-kicker">Tu pedido</p>
                    <h2>Carrito</h2>
                    <p class="fd-drawer__count" data-cart-items-label>0 productos</p>
                </div>
                <button type="button" class="fd-icon-btn" data-close-cart aria-label="Cerrar carrito">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="fd-drawer__list" data-cart-scroll>
                <div data-cart-list></div>
                <div class="fd-empty" data-cart-empty>
                    <p>Tu carrito está vacío.</p>
                    <p>Agrega medicinas desde el catálogo o el buscador.</p>
                </div>
            </div>
            <div class="fd-drawer__foot">
                <p class="fd-cart-rate" data-cart-rate>Tasa BCV del día</p>
                <div class="fd-cart-totals">
                    <div class="fd-cart-total-row">
                        <span>Total USD</span>
                        <strong data-cart-total>$0.00</strong>
                    </div>
                    <div class="fd-cart-total-row fd-cart-total-row--ves">
                        <span>Total VES</span>
                        <strong data-cart-total-ves>Bs. 0,00</strong>
                    </div>
                </div>
                <button type="button" class="fd-btn fd-btn--primary fd-drawer__checkout" data-checkout-pay disabled>Realizar pago</button>
                <p class="fd-drawer__hint">El total en bolívares usa la tasa BCV oficial del día.</p>
            </div>
        </aside>

        {{-- Menú: entra desde abajo, con el control de tema dentro --}}
        <aside
            class="fd-drawer fd-nav-drawer fd-glass"
            data-mobile-nav
            aria-label="Menú"
            aria-hidden="true"
        >
            <button type="button" class="fd-sheet-grab" data-close-nav aria-label="Cerrar menú">
                <span></span>
            </button>

            <div class="fd-sheet-body">
                <p class="fd-sheet-kicker">Menú</p>

                <nav class="fd-sheet-nav">
                    <a href="#inicio" data-close-nav>Inicio</a>
                    <a href="#categorias" data-close-nav>Categorías</a>
                    <a href="#mas-vendidos" data-close-nav>Medicinas</a>
                    <a href="#ofertas" data-close-nav>Ofertas</a>
                    <a href="#receta" data-close-nav>Recetas</a>
                    <a href="#servicios" data-close-nav>Servicios</a>
                </nav>

                <p class="fd-sheet-label">Tu pedido</p>
                <button type="button" class="fd-sheet-item" data-open-modal="track">
                    <span class="fd-sheet-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>
                    </span>
                    <span class="fd-sheet-copy">
                        <strong>Rastrear pedido</strong>
                        <span>Estado y ruta de tu entrega</span>
                    </span>
                    <span class="fd-sheet-chev" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 6 6 6-6 6"/></svg>
                    </span>
                </button>

                <p class="fd-sheet-label">Apariencia</p>
                <div class="fd-sheet-theme">
                    <span class="fd-sheet-copy">
                        <strong>Tema</strong>
                        <span>Claro u oscuro, como prefieras</span>
                    </span>
                    <div class="fd-theme-seg" role="group" aria-label="Tema">
                        <button type="button" data-theme="light">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>
                            Claro
                        </button>
                        <button type="button" data-theme="dark">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a9 9 0 0 1-12-12 9 9 0 1 0 12 12Z"/></svg>
                            Oscuro
                        </button>
                    </div>
                </div>

                <a class="fd-btn fd-btn--primary fd-sheet-cta" href="{{ url('/farmaadmin') }}">Entrar a Farmaadmin</a>
            </div>
        </aside>

        {{-- Ficha de producto: pantalla completa (foto 40% / detalle y acciones 60%) --}}
        <div class="fd-modal fd-modal--full" data-modal="product" role="dialog" aria-modal="true" aria-labelledby="fd-qv-name">
            <div class="fd-pdp fd-glass">
                <div class="fd-pdp__bar">
                    <p class="fd-kicker" style="margin:0;">Ficha</p>
                    <button type="button" class="fd-icon-btn" data-close-modal aria-label="Cerrar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="fd-pdp__media">
                    <span class="fd-pdp__flags">
                        <span class="fd-pdp__badge" data-qv-discount hidden></span>
                        <span class="fd-pdp__badge fd-pdp__badge--rx" data-qv-rx hidden>Requiere récipe</span>
                    </span>
                    <img data-qv-image alt="">
                </div>

                <div class="fd-pdp__info">
                    <p class="fd-pdp__brand" data-qv-meta></p>
                    <h3 class="fd-pdp__name" id="fd-qv-name" data-qv-name></h3>

                    <p class="fd-pdp__price">
                        <strong data-qv-price></strong>
                        <s data-qv-list hidden></s>
                    </p>

                    <div class="fd-pdp__facts">
                        <span class="fd-pdp__fact fd-pdp__fact--ok" data-qv-stock></span>
                        <span class="fd-pdp__fact">Entrega a domicilio el mismo día</span>
                        <span class="fd-pdp__fact">Retiro sin costo en tu sucursal</span>
                    </div>

                    <p class="fd-pdp__desc"><strong>Principio activo:</strong> <span data-qv-ingredient></span></p>
                </div>

                <div class="fd-pdp__cta">
                    <div class="fd-qty" data-qv-qty>
                        <button type="button" data-qv-minus aria-label="Quitar una unidad">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14"/></svg>
                        </button>
                        <span data-qv-qty-value>1</span>
                        <button type="button" data-qv-plus aria-label="Agregar una unidad">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                    </div>

                    <button type="button" class="fd-btn fd-btn--primary" style="flex:1;min-width:0;" data-add-to-cart data-add-from-modal>
                        Agregar al carrito
                    </button>
                </div>
            </div>
        </div>

        <div class="fd-modal" data-modal="recipe" role="dialog" aria-modal="true">
            <div class="fd-modal__panel fd-glass">
                <div style="display:flex;justify-content:space-between;">
                    <h3 style="margin:0;">Subir receta</h3>
                    <button type="button" class="fd-icon-btn" data-close-modal aria-label="Cerrar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <p class="fd-product-card__meta">Describe el pedido o adjunta la receta en el chat de WhatsApp. Un farmacéutico te responde.</p>
                <form data-rx-form style="display:grid;gap:.75rem;margin-top:1rem;">
                    <label class="sr-only" for="rx-note">Detalle</label>
                    <textarea id="rx-note" name="note" rows="4" placeholder="Ej. Losartán 50 mg, 30 tabletas..." style="width:100%;border-radius:1rem;border:1px solid var(--fd-glass-border);padding:.85rem;background:transparent;color:inherit;"></textarea>
                    <input type="file" accept="image/*,.pdf" style="font-size:.85rem;">
                    <button type="submit" class="fd-btn fd-btn--primary">Continuar por WhatsApp</button>
                </form>
            </div>
        </div>

        <div class="fd-modal" data-modal="track" role="dialog" aria-modal="true">
            <div class="fd-modal__panel fd-glass">
                <div style="display:flex;justify-content:space-between;">
                    <h3 style="margin:0;">Rastrear pedido</h3>
                    <button type="button" class="fd-icon-btn" data-close-modal aria-label="Cerrar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <form data-track-form style="display:grid;gap:.75rem;margin-top:1rem;">
                    <label class="sr-only" for="track-code">Número de pedido</label>
                    <input id="track-code" name="code" placeholder="Ej. FD-1842" style="border-radius:1rem;border:1px solid var(--fd-glass-border);padding:.85rem;background:transparent;color:inherit;">
                    <button type="submit" class="fd-btn fd-btn--primary">Consultar por WhatsApp</button>
                </form>
            </div>
        </div>

        <div class="fd-toast fd-glass" data-toast role="status"></div>
    </body>
</html>
