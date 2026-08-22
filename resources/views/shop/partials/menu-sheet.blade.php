@php
    use App\Support\Shop\ShopCatalog;

    $menuCategories = cache()->remember(
        'shop.menu.categories',
        now()->addMinutes(10),
        fn (): array => ShopCatalog::categories(6),
    );

    $shortcuts = [
        [
            'href' => route('shop.search', ['ofertas' => 1]),
            'icon' => 'tag',
            'tone' => 'amber',
            'title' => 'Ofertas',
            'subtitle' => 'Descuentos vigentes hoy',
        ],
        [
            'href' => route('shop.cart'),
            'icon' => 'cart',
            'tone' => 'cyan',
            'title' => 'Mi carrito',
            'subtitle' => 'Revisa y confirma tu pedido',
        ],
        [
            'href' => route('shop.account'),
            'icon' => 'clock',
            'tone' => 'teal',
            'title' => 'Mis pedidos',
            'subtitle' => 'Seguimiento de tus compras',
        ],
    ];
@endphp

<div
    class="sh-backdrop"
    x-show="menuOpen"
    x-cloak
    x-transition.opacity.duration.260ms
    @click="close()"
    aria-hidden="true"
></div>

<nav
    id="sh-menu"
    class="sh-sheet"
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
    @keydown.tab="trapTab($event)"
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

    <p id="sh-menu-heading" class="sh-sheet__kicker">Menú</p>

    @if ($menuCategories !== [])
        <p class="sh-sheet__label">Categorías</p>
        <div class="sh-sheet__group">
            @foreach ($menuCategories as $menuCategory)
                <a
                    href="{{ $menuCategory['slug'] !== '' ? route('shop.category', $menuCategory['slug']) : route('shop.search', ['cat' => $menuCategory['id']]) }}"
                    class="sh-sheet__item"
                    wire:navigate
                    @click="close()"
                >
                    <span class="sh-sheet__icon sh-sheet__icon--cyan" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'grid'])
                    </span>
                    <span class="sh-sheet__copy">
                        <strong>{{ $menuCategory['name'] }}</strong>
                        <span>{{ $menuCategory['product_count'] }} productos</span>
                    </span>
                    <span class="sh-sheet__chevron" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'chevron'])
                    </span>
                </a>
            @endforeach

            <a href="{{ route('shop.search') }}" class="sh-sheet__item" wire:navigate @click="close()">
                <span class="sh-sheet__icon sh-sheet__icon--muted" aria-hidden="true">
                    @include('shop.partials.icon', ['icon' => 'sliders'])
                </span>
                <span class="sh-sheet__copy">
                    <strong>Ver todo el catálogo</strong>
                    <span>Filtra por categoría y precio</span>
                </span>
                <span class="sh-sheet__chevron" aria-hidden="true">
                    @include('shop.partials.icon', ['icon' => 'chevron'])
                </span>
            </a>
        </div>
    @endif

    <p class="sh-sheet__label">Accesos</p>
    <div class="sh-sheet__group">
        @foreach ($shortcuts as $shortcut)
            <a href="{{ $shortcut['href'] }}" class="sh-sheet__item" wire:navigate @click="close()">
                <span class="sh-sheet__icon sh-sheet__icon--{{ $shortcut['tone'] }}" aria-hidden="true">
                    @include('shop.partials.icon', ['icon' => $shortcut['icon']])
                </span>
                <span class="sh-sheet__copy">
                    <strong>{{ $shortcut['title'] }}</strong>
                    <span>{{ $shortcut['subtitle'] }}</span>
                </span>
                <span class="sh-sheet__chevron" aria-hidden="true">
                    @include('shop.partials.icon', ['icon' => 'chevron'])
                </span>
            </a>
        @endforeach

        <a
            href="{{ $shopWhatsappUrl }}"
            class="sh-sheet__item"
            target="_blank"
            rel="noopener"
            @click="close()"
        >
            <span class="sh-sheet__icon sh-sheet__icon--teal" aria-hidden="true">
                @include('shop.partials.icon', ['icon' => 'whatsapp'])
            </span>
            <span class="sh-sheet__copy">
                <strong>Hablar con un farmacéutico</strong>
                <span>{{ $shopWhatsappDisplay }}</span>
            </span>
            <span class="sh-sheet__chevron" aria-hidden="true">
                @include('shop.partials.icon', ['icon' => 'chevron'])
            </span>
        </a>
    </div>

    <p class="sh-sheet__label">Preferencias</p>
    <div class="sh-sheet__theme">
        <div class="sh-sheet__theme-copy">
            <strong>Apariencia</strong>
            <span>Claro u oscuro, como más te guste</span>
        </div>
        <button
            type="button"
            class="sh-switch"
            :class="{ 'is-on': $store.shop.dark }"
            @click="$store.shop.toggleTheme()"
            role="switch"
            :aria-checked="$store.shop.dark.toString()"
            aria-label="Cambiar entre modo claro y oscuro"
        >
            <span class="sh-switch__knob" aria-hidden="true">
                <template x-if="$store.shop.dark">
                    @include('shop.partials.icon', ['icon' => 'moon'])
                </template>
                <template x-if="! $store.shop.dark">
                    @include('shop.partials.icon', ['icon' => 'sun'])
                </template>
            </span>
        </button>
    </div>

    <template x-if="$store.shop.canInstall">
        <div class="sh-sheet__group" style="margin-top:0.4rem;">
            <button type="button" class="sh-sheet__item" @click="$store.shop.install(); close()">
                <span class="sh-sheet__icon sh-sheet__icon--amber" aria-hidden="true">
                    @include('shop.partials.icon', ['icon' => 'download'])
                </span>
                <span class="sh-sheet__copy">
                    <strong>Instalar la app</strong>
                    <span>Ábrela desde tu pantalla de inicio</span>
                </span>
            </button>
        </div>
    </template>

    <div class="sh-sheet__actions">
        <button type="button" class="sh-btn sh-btn--quiet sh-btn--block" @click="close()">Cerrar</button>
    </div>
</nav>
