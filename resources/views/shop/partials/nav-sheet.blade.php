@php
    $current = $tab ?? '';

    $labels = [
        'home' => 'Inicio',
        'categories' => 'Categorías',
        'search' => 'Buscar',
        'cart' => 'Carrito',
        'account' => 'Cuenta',
    ];

    $here = $labels[$current] ?? 'Menú';

    $destinations = [
        [
            'key' => 'home',
            'href' => route('shop.home'),
            'icon' => 'home',
            'tone' => 'teal',
            'label' => 'Inicio',
            'hint' => 'Para ti hoy',
            'wide' => false,
        ],
        [
            'key' => 'search',
            'href' => route('shop.search'),
            'icon' => 'search',
            'tone' => 'cyan',
            'label' => 'Buscar',
            'hint' => 'Nombre o marca',
            'wide' => false,
        ],
        [
            'key' => 'categories',
            'href' => route('shop.categories'),
            'icon' => 'grid',
            'tone' => 'amber',
            'label' => 'Categorías',
            'hint' => 'Por tipo',
            'wide' => false,
        ],
        [
            'key' => 'cart',
            'href' => route('shop.cart'),
            'icon' => 'cart',
            'tone' => 'rose',
            'label' => 'Carrito',
            'hint' => 'Tus productos',
            'wide' => true,
            'badge' => true,
        ],
        [
            'key' => 'account',
            'href' => route('shop.account'),
            'icon' => 'user',
            'tone' => 'slate',
            'label' => 'Cuenta',
            'hint' => 'Pedidos y ayuda',
            'wide' => true,
        ],
    ];
@endphp

<button
    type="button"
    class="sh-nav-handle"
    x-show="! menuOpen"
    x-cloak
    @click="open()"
    :aria-expanded="menuOpen.toString()"
    aria-controls="sh-menu"
    aria-label="Abrir menú de la app"
>
    <span class="sh-nav-handle__grip" aria-hidden="true"></span>
    <span class="sh-nav-handle__here">{{ $here }}</span>
    <template x-if="$store.shop.cartCount > 0">
        <span class="sh-nav-handle__cart">
            @include('shop.partials.icon', ['icon' => 'bag'])
            <span x-text="$store.shop.cartCount > 99 ? '99+' : $store.shop.cartCount"></span>
        </span>
    </template>
    <span class="sh-nav-handle__chevron" aria-hidden="true">
        @include('shop.partials.icon', ['icon' => 'back'])
    </span>
</button>

<div
    class="sh-backdrop sh-backdrop--nav"
    x-show="menuOpen"
    x-cloak
    x-transition.opacity.duration.280ms
    @click="close()"
    aria-hidden="true"
></div>

<nav
    id="sh-menu"
    class="sh-sheet sh-sheet--nav"
    x-ref="menuSheet"
    x-show="menuOpen"
    x-cloak
    x-bind:class="{ 'is-dragging': dragging }"
    x-bind:style="sheetStyle()"
    x-transition:enter="sh-sheet-anim"
    x-transition:enter-start="sh-sheet-anim-start"
    x-transition:enter-end="sh-sheet-anim-end"
    x-transition:leave="sh-sheet-anim sh-sheet-anim-leave"
    x-transition:leave-start="sh-sheet-anim-end"
    x-transition:leave-end="sh-sheet-anim-start"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sh-menu-heading"
    tabindex="-1"
    @click.stop
    @keydown.escape.window="close()"
>
    <button
        type="button"
        class="sh-sheet__grab"
        tabindex="-1"
        aria-label="Cerrar menú deslizando hacia abajo"
        @pointerdown="onDragStart($event)"
        @pointermove="onDragMove($event)"
        @pointerup="onDragEnd()"
        @pointercancel="onDragEnd()"
    >
        <span class="sh-sheet__handle"></span>
    </button>

    <div class="sh-nav-head">
        <div>
            <p id="sh-menu-heading" class="sh-nav-head__title">Farmadoc</p>
            <p class="sh-nav-head__sub">¿A dónde vamos?</p>
        </div>
        <button type="button" class="sh-icon-btn sh-icon-btn--ghost" @click="close()" aria-label="Cerrar menú">
            @include('shop.partials.icon', ['icon' => 'close'])
        </button>
    </div>

    <div class="sh-nav-grid">
        @foreach ($destinations as $destination)
            <a
                href="{{ $destination['href'] }}"
                wire:navigate.hover
                @click="close()"
                @class([
                    'sh-nav-tile',
                    'sh-nav-tile--'.$destination['tone'],
                    'sh-nav-tile--wide' => $destination['wide'],
                    'is-active' => $current === $destination['key'],
                ])
                @if ($current === $destination['key']) aria-current="page" @endif
            >
                <span class="sh-nav-tile__icon" aria-hidden="true">
                    @include('shop.partials.icon', ['icon' => $destination['icon']])
                </span>

                <span class="sh-nav-tile__copy">
                    <strong>{{ $destination['label'] }}</strong>
                    @if (($destination['badge'] ?? false))
                        <span x-text="$store.shop.cartCount > 0 ? ($store.shop.cartCount + ' en el carrito') : 'Vacío por ahora'"></span>
                    @else
                        <span>{{ $destination['hint'] }}</span>
                    @endif
                </span>

                @if ($current === $destination['key'])
                    <span class="sh-nav-tile__now">Aquí</span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
