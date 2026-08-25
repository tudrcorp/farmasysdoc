@php
    $hasDiscount = $product['discount_percent'] > 0;
    $stock = (int) floor($product['stock_available']);

    // Atributos que existan de verdad; nada de rellenar con guiones.
    $chips = collect([
        $product['presentation'] ?? null,
        $concentration,
        $netContent,
    ])->filter(fn (?string $value): bool => filled($value) && $value !== '—')->values();
@endphp

<div class="sh-pdp">
    {{-- Barra superior --}}
    <div class="sh-pdp__bar">
        {{-- Vuelve por historial para conservar la pantalla y el scroll previos. --}}
        <button
            type="button"
            class="sh-icon-btn sh-icon-btn--ghost"
            aria-label="Volver"
            @click="window.history.length > 1
                ? window.history.back()
                : window.location.assign(@js(route('shop.home')))"
        >
            @include('shop.partials.icon', ['icon' => 'back'])
        </button>

        <a href="{{ route('shop.cart') }}" wire:navigate class="sh-icon-btn sh-icon-btn--ghost" aria-label="Ver carrito">
            @include('shop.partials.icon', ['icon' => 'bag'])
            <template x-if="$store.shop.cartCount > 0">
                <span class="sh-icon-btn__dot" x-text="$store.shop.cartCount > 99 ? '99+' : $store.shop.cartCount"></span>
            </template>
        </a>
    </div>

    {{-- Imagen: absorbe el alto disponible --}}
    <div class="sh-pdp__media">
        <div class="sh-pdp__flags">
            @if ($hasDiscount)
                <span class="sh-pill sh-pill--discount">-{{ (int) $product['discount_percent'] }}%</span>
            @endif
            @if ($product['requires_prescription'])
                <span class="sh-pill sh-pill--rx">Requiere récipe</span>
            @endif
        </div>

        <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" fetchpriority="high" decoding="async">
    </div>

    {{-- Datos --}}
    <div class="sh-pdp__info">
        <p class="sh-pdp__brand">
            {{ $product['brand'] !== '—' ? $product['brand'] : ($product['category'] ?? 'Farmadoc') }}
        </p>

        <h1 class="sh-pdp__name">{{ $product['name'] }}</h1>

        <div class="sh-pdp__price">
            <div class="sh-pdp__price-now">
                <strong>{{ $money->formatUsd($product['effective_price']) }}</strong>
                @if ($money->formatVes($product['effective_price']))
                    <span class="sh-money__ves">{{ $money->formatVes($product['effective_price']) }}</span>
                @endif
            </div>
            @if ($hasDiscount)
                <s>{{ $money->formatUsd($product['sale_price']) }}</s>
                <span class="sh-pill sh-pill--ok">
                    Ahorras {{ $money->formatUsd($product['sale_price'] - $product['effective_price']) }}
                </span>
            @endif
        </div>

        @if ($chips->isNotEmpty())
            <div class="sh-pdp__chips">
                @foreach ($chips as $chip)
                    <span class="sh-pdp__chip">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        <div class="sh-pdp__facts">
            <div class="sh-pdp__fact sh-pdp__fact--ok">
                @include('shop.partials.icon', ['icon' => 'check'])
                <span>{{ $stock > 10 ? 'Disponible en tienda' : 'Últimas '.$stock.' unidades' }}</span>
            </div>
            <div class="sh-pdp__fact">
                @include('shop.partials.icon', ['icon' => 'truck'])
                <span>Entrega a domicilio el mismo día</span>
            </div>
            <div class="sh-pdp__fact">
                @include('shop.partials.icon', ['icon' => 'store'])
                <span>Retiro sin costo en tu sucursal</span>
            </div>
        </div>

        @if (filled($description))
            <p class="sh-pdp__desc">{{ $description }}</p>
        @elseif ($product['active_ingredient'] !== '—')
            <p class="sh-pdp__desc">Principio activo: {{ $product['active_ingredient'] }}.</p>
        @endif
    </div>

    {{-- Acción única de la pantalla --}}
    <div class="sh-pdp__cta">
        <div class="sh-stepper" style="flex-shrink:0;">
            <button
                type="button"
                wire:click="decreaseQuantity"
                @disabled($quantity <= 1)
                aria-label="Quitar una unidad"
            >
                @include('shop.partials.icon', ['icon' => 'minus'])
            </button>
            <span class="sh-stepper__value">{{ (int) $quantity }}</span>
            <button
                type="button"
                wire:click="increaseQuantity"
                @disabled($quantity >= $maxQuantity)
                aria-label="Agregar una unidad"
            >
                @include('shop.partials.icon', ['icon' => 'plus'])
            </button>
        </div>

        <button
            type="button"
            class="sh-btn sh-btn--primary"
            style="flex:1;min-width:0;"
            wire:click="addSelectionToCart"
            wire:loading.attr="disabled"
            wire:target="addSelectionToCart"
        >
            <span wire:loading.remove wire:target="addSelectionToCart" class="sh-pdp__add-copy">
                Agregar · {{ $money->formatUsd($lineTotal) }}
                @if ($money->formatVes($lineTotal))
                    <small>{{ $money->formatVes($lineTotal) }}</small>
                @endif
            </span>
            <span wire:loading wire:target="addSelectionToCart">Agregando…</span>
        </button>
    </div>
</div>
