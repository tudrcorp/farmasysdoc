@php
    $cartQuantities = $cartQuantities ?? [];
@endphp

<div x-data="shopSearchRecents()">
    @include('shop.partials.header', ['title' => 'Buscar', 'showCart' => true])

    <main class="sh-main">
        <div class="sh-page sh-page--search">
            <div class="sh-search-sticky">
                <div class="sh-searchbar">
                    @include('shop.partials.icon', ['icon' => 'search'])
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="term"
                        placeholder="Medicina, marca o principio activo"
                        autocomplete="off"
                        enterkeyhint="search"
                        inputmode="search"
                        aria-label="Buscar productos"
                        x-ref="query"
                        @input="remember($event.target.value)"
                    >
                    <div wire:loading wire:target="term" style="flex-shrink:0;">
                        <span class="sh-skeleton" style="display:block;width:1.1rem;height:1.1rem;border-radius:50%;"></span>
                    </div>
                    @if ($term !== '')
                        <button
                            type="button"
                            wire:click="resetSearch"
                            wire:loading.remove
                            wire:target="term"
                            @click="clearInput()"
                            aria-label="Borrar búsqueda"
                            style="color:var(--sh-faint);display:flex;"
                        >
                            @include('shop.partials.icon', ['icon' => 'close'])
                        </button>
                    @endif
                </div>

                <div class="sh-chips" style="margin-top:0.7rem;padding-inline:0;margin-inline:0;">
                    <button
                        type="button"
                        @class(['sh-chip', 'is-active' => $onlyOffers])
                        wire:click="$toggle('onlyOffers')"
                    >
                        @include('shop.partials.icon', ['icon' => 'tag'])
                        Ofertas
                    </button>

                    @foreach ($categories as $category)
                        <button
                            type="button"
                            @class(['sh-chip', 'is-active' => $categoryId === $category['id']])
                            wire:click="toggleCategory({{ $category['id'] }})"
                            wire:key="chip-{{ $category['id'] }}"
                        >
                            {{ $category['name'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;margin-top:0.85rem;">
                <p class="sh-sub" style="margin:0;">
                    @if ($tooShort)
                        Sigue escribiendo
                    @elseif (count($results) === 0)
                        Sin resultados
                    @else
                        {{ count($results) }}{{ $hasMore ? '+' : '' }} {{ \Illuminate\Support\Str::plural('producto', count($results)) }}
                    @endif
                </p>

                @include('shop.partials.sort-sheet', ['sortOptions' => $sortOptions, 'sort' => $sort])
            </div>

            @if ($this->hasActiveFilters())
                <button type="button" wire:click="clearFilters" class="sh-btn sh-btn--quiet" style="margin-top:0.75rem;width:100%;">
                    @include('shop.partials.icon', ['icon' => 'close'])
                    Quitar filtros
                </button>
            @endif

            <div
                x-show="showRecents && recents.length > 0"
                x-cloak
                class="sh-section"
                style="margin-top:1rem;"
            >
                <div class="sh-section__head">
                    <h3 class="sh-h2">Recientes</h3>
                    <button type="button" class="sh-section__link" @click="forgetAll()">Borrar</button>
                </div>
                <div class="sh-chips" style="padding-inline:0;margin-inline:0;">
                    <template x-for="recent in recents" :key="recent">
                        <button type="button" class="sh-chip" @click="apply(recent)" x-text="recent"></button>
                    </template>
                </div>
            </div>

            <div style="margin-top:1rem;">
                @if ($tooShort)
                    <div class="sh-empty" style="padding-block:2rem;">
                        <span class="sh-empty__art" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'search'])
                        </span>
                        <h3>Escribe al menos 2 letras</h3>
                        <p>O el código de barras completo para ir directo al producto.</p>
                    </div>
                @elseif ($results === [])
                    <div class="sh-empty" x-show="! showRecents || recents.length === 0">
                        <span class="sh-empty__art" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'search'])
                        </span>
                        <h3>No encontramos nada</h3>
                        <p>
                            @if ($term !== '')
                                Prueba con otro nombre, la marca o el principio activo.
                            @else
                                Empieza a escribir o elige una categoría.
                            @endif
                        </p>
                    </div>
                @else
                    <div class="sh-grid">
                        @foreach ($results as $product)
                            @include('shop.partials.product-card', [
                                'product' => $product,
                                'cartQty' => (int) ($cartQuantities[$product['id']] ?? 0),
                            ])
                        @endforeach
                    </div>

                    @if ($hasMore)
                        <div wire:intersect="loadMore" class="sh-infinite">
                            <span class="sh-skeleton" style="width:100%;height:2.6rem;"></span>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </main>
</div>
