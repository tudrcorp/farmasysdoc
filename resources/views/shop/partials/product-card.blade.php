@php
    /**
     * Tarjeta de producto. Toda la tarjeta navega al detalle; el stepper muta
     * el carrito sin reconsultar el catálogo (el recuento vive en Alpine).
     *
     * @var array<string, mixed> $product
     * @var bool                 $inCart
     * @var float|int            $cartQty
     * @var bool                 $priority  First-screen images skip lazy-load.
     */
    $product = $product;
    $cartQty = (int) ($cartQty ?? (($inCart ?? false) ? 1 : 0));
    $hasDiscount = ($product['discount_percent'] ?? 0) > 0;
    $priority = (bool) ($priority ?? false);
@endphp

<article class="sh-prod" wire:key="prod-{{ $product['id'] }}" x-data="{ qty: {{ $cartQty }} }">
    <a
        href="{{ route('shop.product', $product['id']) }}"
        wire:navigate.hover
        class="sh-prod__media"
        aria-label="Ver {{ $product['name'] }}"
    >
        <img
            src="{{ $product['image_url'] }}"
            alt="{{ $product['name'] }}"
            width="320"
            height="320"
            decoding="async"
            @if ($priority)
                fetchpriority="high"
            @else
                loading="lazy"
            @endif
        >

        <div class="sh-prod__flags">
            <span>
                @if ($hasDiscount)
                    <span class="sh-pill sh-pill--discount">-{{ (int) $product['discount_percent'] }}%</span>
                @endif
            </span>
            <span>
                @if ($product['requires_prescription'])
                    <span class="sh-pill sh-pill--rx" title="Requiere récipe médico">Rx</span>
                @endif
            </span>
        </div>
    </a>

    <div class="sh-prod__body">
        <p class="sh-prod__brand">{{ $product['brand'] !== '—' ? $product['brand'] : ($product['category'] ?? 'Farmadoc') }}</p>

        <a href="{{ route('shop.product', $product['id']) }}" wire:navigate.hover class="sh-prod__name">
            {{ $product['name'] }}
        </a>

        <div class="sh-prod__foot">
            <div class="sh-prod__price">
                <strong>${{ number_format($product['effective_price'], 2) }}</strong>
                @if ($hasDiscount)
                    <s>${{ number_format($product['sale_price'], 2) }}</s>
                @endif
            </div>

            <button
                type="button"
                class="sh-add"
                x-show="qty < 1"
                x-cloak
                wire:click="addToCart({{ $product['id'] }})"
                @click="qty = 1"
                aria-label="Agregar {{ $product['name'] }} al carrito"
            >
                @include('shop.partials.icon', ['icon' => 'plus'])
            </button>

            <div class="sh-stepper sh-stepper--card" x-show="qty > 0" x-cloak>
                <button
                    type="button"
                    wire:click="decrement({{ $product['id'] }})"
                    @click="qty = Math.max(0, qty - 1)"
                    aria-label="Quitar una unidad"
                >
                    @include('shop.partials.icon', ['icon' => 'minus'])
                </button>
                <span class="sh-stepper__value" x-text="qty">{{ $cartQty }}</span>
                <button
                    type="button"
                    wire:click="increment({{ $product['id'] }})"
                    @click="qty = qty + 1"
                    aria-label="Agregar una unidad"
                >
                    @include('shop.partials.icon', ['icon' => 'plus'])
                </button>
            </div>
        </div>
    </div>
</article>
