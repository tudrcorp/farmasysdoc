<div
    class="sh-home-search"
    x-data="{
        top: 0,
        sync() {
            const bar = this.$refs.bar;
            if (! bar) {
                return;
            }
            this.top = Math.round(bar.getBoundingClientRect().bottom + 8);
        },
    }"
    x-init="sync()"
    @resize.window.passive="sync()"
    @scroll.window.passive="sync()"
>
    <form class="sh-searchbar" x-ref="bar" wire:submit="openFullSearch" role="search">
        @include('shop.partials.icon', ['icon' => 'search'])
        <input
            type="search"
            wire:model.live.debounce.280ms="term"
            placeholder="Busca medicinas, marcas…"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            enterkeyhint="search"
            inputmode="search"
            maxlength="80"
            aria-label="Buscar productos"
            aria-controls="home-search-results"
            @focus="sync()"
        >
        <span wire:loading wire:target="term" class="sh-home-search__spinner" aria-hidden="true"></span>
        @if (trim($term) !== '')
            <button
                type="button"
                class="sh-home-search__clear"
                wire:click="clear"
                wire:loading.remove
                wire:target="term"
                aria-label="Borrar búsqueda"
            >
                @include('shop.partials.icon', ['icon' => 'close'])
            </button>
        @endif
    </form>

    @if ($searching)
        <div
            id="home-search-results"
            class="sh-home-search__panel"
            :style="{ top: top + 'px' }"
            role="listbox"
            aria-label="Resultados de búsqueda"
        >
            @if ($tooShort)
                <p class="sh-home-search__hint">Sigue escribiendo para buscar</p>
            @elseif ($results === [])
                <div class="sh-empty" style="padding-block:1.6rem;">
                    <span class="sh-empty__art" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'search'])
                    </span>
                    <h3>Sin resultados</h3>
                    <p>Prueba con el nombre, la marca o el principio activo.</p>
                </div>
            @else
                <ul class="sh-suggest">
                    @foreach ($results as $product)
                        <li wire:key="home-hit-{{ $product['id'] }}">
                            <a
                                href="{{ route('shop.product', $product['id']) }}"
                                wire:navigate
                                class="sh-suggest__row"
                                role="option"
                            >
                                <span class="sh-suggest__media">
                                    <img
                                        src="{{ $product['image_url'] }}"
                                        alt=""
                                        width="56"
                                        height="56"
                                        decoding="async"
                                        @if ($loop->index < 4)
                                            fetchpriority="high"
                                        @else
                                            loading="lazy"
                                        @endif
                                    >
                                </span>
                                <span class="sh-suggest__copy">
                                    <strong>{{ $product['name'] }}</strong>
                                    <span>
                                        {{ $product['brand'] !== '—' ? $product['brand'] : ($product['category'] ?? 'Farmadoc') }}
                                    </span>
                                </span>
                                <span class="sh-suggest__price">
                                    ${{ number_format($product['effective_price'], 2) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <button
                    type="button"
                    class="sh-btn sh-btn--quiet"
                    style="width:100%;margin-top:0.85rem;"
                    wire:click="openFullSearch"
                >
                    Ver todos los resultados
                    @include('shop.partials.icon', ['icon' => 'chevron'])
                </button>
            @endif
        </div>
    @endif
</div>
