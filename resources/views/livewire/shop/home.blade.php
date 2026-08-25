@php
    $cartQuantities = $cartQuantities ?? [];
    $heroProduct = $offers[0] ?? ($bestsellers[0] ?? null);
@endphp

<div
    x-data="{ searching: false }"
    x-on:home-search-toggle.window="
        searching = Boolean($event.detail?.open);
        document.documentElement.classList.toggle('sh-search-lock', searching);
    "
    x-on:livewire:navigating.window="document.documentElement.classList.remove('sh-search-lock')"
>
    @include('shop.partials.header', ['title' => null])

    <main class="sh-main">
        <div class="sh-page">
            {{-- Saludo --}}
            <section style="padding-top:1.1rem;">
                <p class="sh-eyebrow">{{ $greeting }}</p>
                <h2 class="sh-h1">¿Qué necesitas hoy?</h2>
                @if ($usdVesRate)
                    <p class="sh-pill sh-pill--ok" style="margin-top:0.55rem;width:fit-content;">
                        BCV {{ number_format($usdVesRate, 2, ',', '.') }}
                    </p>
                @endif
            </section>

            <livewire:shop.home-search wire:key="home-search" />

            {{-- Banner principal --}}
            @if ($heroProduct)
                <section class="sh-hero" style="margin-top:1.15rem;">
                    <span class="sh-hero__eyebrow">
                        @if ($hasOffers)
                            @include('shop.partials.icon', ['icon' => 'tag'])
                            Oferta destacada
                        @else
                            @include('shop.partials.icon', ['icon' => 'spark'])
                            Lo más pedido
                        @endif
                    </span>

                    <h3 class="sh-hero__title">{{ \Illuminate\Support\Str::limit($heroProduct['name'], 42) }}</h3>

                    <p class="sh-hero__text">
                        @if ($heroProduct['discount_percent'] > 0)
                            {{ (int) $heroProduct['discount_percent'] }}% de descuento · antes ${{ number_format($heroProduct['sale_price'], 2) }}
                        @else
                            Disponible con entrega a domicilio o retiro en sucursal.
                        @endif
                    </p>

                    <a href="{{ route('shop.product', $heroProduct['id']) }}" wire:navigate.hover class="sh-hero__cta">
                        Ver por ${{ number_format($heroProduct['effective_price'], 2) }}
                        @include('shop.partials.icon', ['icon' => 'chevron'])
                    </a>
                </section>
            @endif

            {{-- Categorías --}}
            @if ($categories !== [])
                <section class="sh-section">
                    <div class="sh-section__head">
                        <h3 class="sh-h2">Categorías</h3>
                        <a href="{{ route('shop.categories') }}" wire:navigate.hover class="sh-section__link">
                            Ver todo
                            @include('shop.partials.icon', ['icon' => 'chevron'])
                        </a>
                    </div>

                    <div class="sh-cats">
                        @foreach (array_slice($categories, 0, 8) as $category)
                            <a
                                href="{{ $category['slug'] !== '' ? route('shop.category', $category['slug']) : route('shop.search', ['cat' => $category['id']]) }}"
                                wire:navigate.hover
                                class="sh-cat"
                                wire:key="cat-{{ $category['id'] }}"
                            >
                                <span class="sh-cat__icon" aria-hidden="true">
                                    @if ($category['image_url'])
                                        <img src="{{ $category['image_url'] }}" alt="" decoding="async">
                                    @else
                                        @include('shop.partials.category-glyph', ['category' => $category])
                                    @endif
                                </span>
                                <span class="sh-cat__name">{{ $category['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Ofertas / recomendados --}}
            @if ($offers !== [])
                <section class="sh-section">
                    <div class="sh-section__head">
                        <h3 class="sh-h2">{{ $hasOffers ? 'Ofertas de la semana' : 'Recomendados' }}</h3>
                        <a href="{{ route('shop.search', ['ofertas' => 1]) }}" wire:navigate class="sh-section__link">
                            Ver todo
                            @include('shop.partials.icon', ['icon' => 'chevron'])
                        </a>
                    </div>

                    <div class="sh-rail">
                        @foreach ($offers as $product)
                            @include('shop.partials.product-card', [
                                'product' => $product,
                                'cartQty' => (int) ($cartQuantities[$product['id']] ?? 0),
                                'priority' => $loop->index < 6,
                            ])
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Más vendidos --}}
            @if ($bestsellers !== [])
                <section class="sh-section">
                    <div class="sh-section__head">
                        <h3 class="sh-h2">Los más vendidos</h3>
                        <a href="{{ route('shop.search') }}" wire:navigate.hover class="sh-section__link">
                            Ver todo
                            @include('shop.partials.icon', ['icon' => 'chevron'])
                        </a>
                    </div>

                    <div class="sh-grid">
                        @foreach (array_slice($bestsellers, 0, 6) as $product)
                            @include('shop.partials.product-card', [
                                'product' => $product,
                                'cartQty' => (int) ($cartQuantities[$product['id']] ?? 0),
                                'priority' => $loop->index < 6,
                            ])
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($categories === [] && $bestsellers === [])
                <div class="sh-empty">
                    <span class="sh-empty__art" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'box'])
                    </span>
                    <h3>Catálogo en camino</h3>
                    <p>Estamos cargando los productos disponibles. Vuelve en un momento.</p>
                </div>
            @endif
        </div>
    </main>
</div>
