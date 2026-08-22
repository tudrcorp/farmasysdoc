@php
    /**
     * Cabecera de pantalla. Con `back` muestra la flecha de retroceso, si no el logo.
     *
     * @var string|null $title    Título centrado.
     * @var string|null $back     URL de respaldo si no hay historial.
     * @var bool        $showCart Muestra el acceso al carrito a la derecha.
     */
    $title = $title ?? null;
    $back = $back ?? null;
    $showCart = $showCart ?? true;
    $fallback = is_string($back) && $back !== '' ? $back : route('shop.home');
@endphp

<header class="sh-header" :class="{ 'is-scrolled': scrolled }">
    <div class="sh-header__row">
        <div>
            @if ($back)
                <button
                    type="button"
                    class="sh-icon-btn"
                    aria-label="Volver"
                    onclick="window.history.length > 1 ? window.history.back() : window.location.assign(@js($fallback))"
                >
                    @include('shop.partials.icon', ['icon' => 'back'])
                </button>
            @else
                <button
                    type="button"
                    class="sh-icon-btn"
                    @click="open()"
                    aria-label="Abrir menú"
                    :aria-expanded="menuOpen.toString()"
                    aria-controls="sh-menu"
                >
                    @include('shop.partials.icon', ['icon' => 'grid'])
                </button>
            @endif
        </div>

        @if ($title)
            <h1 class="sh-header__title">{{ $title }}</h1>
        @else
            <div class="sh-header__brand">
                <img
                    src="{{ asset('images/logos/farmadoc-ligth.png') }}"
                    alt="Farmadoc"
                    width="280"
                    height="58"
                    x-show="! $store.shop.dark"
                >
                <img
                    src="{{ asset('images/logos/farmadoc-dark.png') }}"
                    alt="Farmadoc"
                    width="280"
                    height="58"
                    x-show="$store.shop.dark"
                    x-cloak
                >
            </div>
        @endif

        <div>
            @if ($showCart)
                <a
                    href="{{ route('shop.cart') }}"
                    class="sh-icon-btn"
                    wire:navigate.hover
                    aria-label="Ver carrito"
                >
                    @include('shop.partials.icon', ['icon' => 'bag'])
                    <template x-if="$store.shop.cartCount > 0">
                        <span
                            class="sh-icon-btn__dot"
                            x-text="$store.shop.cartCount > 99 ? '99+' : $store.shop.cartCount"
                        ></span>
                    </template>
                </a>
            @endif
        </div>
    </div>
</header>
