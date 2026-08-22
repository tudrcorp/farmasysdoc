@props(['product'])

@php
    $discount = (float) ($product['discount_percent'] ?? 0);
    $hasDiscount = $discount > 0;
@endphp

<article
    {{ $attributes->class(['fd-product-card fd-glass']) }}
    data-product-card
    data-product="{{ json_encode($product, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
>
    <div class="fd-product-card__media">
        @if ($hasDiscount)
            <span class="fd-badge-off">{{ (int) round($discount) }}% OFF</span>
        @endif
        <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" loading="lazy">
    </div>
    <h3>{{ $product['name'] }}</h3>
    <p class="fd-product-card__meta">{{ $product['brand'] }} · {{ $product['presentation'] }}</p>
    <div class="fd-product-card__row">
        <div class="fd-price">
            {{ '$'.number_format((float) $product['effective_price'], 2) }}
            @if ($hasDiscount)
                <s>{{ '$'.number_format((float) $product['sale_price'], 2) }}</s>
            @endif
        </div>
        <button type="button" class="fd-add" data-add-to-cart aria-label="Agregar {{ $product['name'] }} al carrito">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path d="M6 6h15l-1.5 9h-12z"></path>
                <circle cx="9" cy="20" r="1"></circle>
                <circle cx="18" cy="20" r="1"></circle>
                <path d="M6 6 5 3H2"></path>
            </svg>
        </button>
    </div>
</article>
