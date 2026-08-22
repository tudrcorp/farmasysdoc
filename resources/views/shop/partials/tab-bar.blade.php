@php
    $current = $tab ?? '';
@endphp

<nav class="sh-tabbar" aria-label="Navegación principal">
    <a
        href="{{ route('shop.home') }}"
        wire:navigate.hover
        @class(['sh-tab', 'is-active' => $current === 'home'])
        @if ($current === 'home') aria-current="page" @endif
    >
        @include('shop.partials.icon', ['icon' => 'home'])
        <span class="sh-tab__label">Inicio</span>
    </a>

    <a
        href="{{ route('shop.categories') }}"
        wire:navigate.hover
        @class(['sh-tab', 'is-active' => $current === 'categories'])
        @if ($current === 'categories') aria-current="page" @endif
    >
        @include('shop.partials.icon', ['icon' => 'grid'])
        <span class="sh-tab__label">Categorías</span>
    </a>

    <a
        href="{{ route('shop.search') }}"
        wire:navigate.hover
        @class(['sh-tab', 'is-active' => $current === 'search'])
        @if ($current === 'search') aria-current="page" @endif
    >
        @include('shop.partials.icon', ['icon' => 'search'])
        <span class="sh-tab__label">Buscar</span>
    </a>

    <a
        href="{{ route('shop.cart') }}"
        wire:navigate.hover
        @class(['sh-tab', 'is-active' => $current === 'cart'])
        @if ($current === 'cart') aria-current="page" @endif
    >
        @include('shop.partials.icon', ['icon' => 'cart'])
        <span class="sh-tab__label">Carrito</span>
        <template x-if="$store.shop.cartCount > 0">
            <span
                class="sh-tab__badge"
                x-text="$store.shop.cartCount > 99 ? '99+' : $store.shop.cartCount"
            ></span>
        </template>
    </a>

    <a
        href="{{ route('shop.account') }}"
        wire:navigate.hover
        @class(['sh-tab', 'is-active' => $current === 'account'])
        @if ($current === 'account') aria-current="page" @endif
    >
        @include('shop.partials.icon', ['icon' => 'user'])
        <span class="sh-tab__label">Cuenta</span>
    </a>
</nav>
