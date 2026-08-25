@php
    $cartQuantities = $cartQuantities ?? [];
@endphp

<div>
    @include('shop.partials.header', ['title' => $category->name, 'back' => route('shop.categories')])

    <main class="sh-main">
        <div class="sh-page" style="padding-top:1rem;">
            <section style="display:flex;align-items:center;gap:0.85rem;">
                <span class="sh-cat__icon" style="width:3.9rem;height:3.9rem;flex-shrink:0;" aria-hidden="true">
                    @if (filled($category->image))
                        <img src="{{ \App\Services\Products\CatalogImageOptimizer::url($category->image) }}" alt="" fetchpriority="high" decoding="async">
                    @else
                        @include('shop.partials.category-glyph', ['category' => [
                            'name' => $category->name,
                            'is_medication' => $category->is_medication,
                        ]])
                    @endif
                </span>

                <div style="min-width:0;">
                    <p class="sh-eyebrow">Categoría</p>
                    <h2 class="sh-h1" style="font-size:1.35rem;">{{ $category->name }}</h2>
                    <p class="sh-sub">{{ count($results) }}{{ $hasMore ? '+' : '' }} productos disponibles</p>
                </div>
            </section>

            @if (filled($category->description))
                <p class="sh-sub" style="margin-top:0.85rem;">{{ $category->description }}</p>
            @endif

            {{-- Filtros --}}
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.7rem;margin-top:1.05rem;">
                <button
                    type="button"
                    @class(['sh-chip', 'is-active' => $onlyOffers])
                    wire:click="$toggle('onlyOffers')"
                >
                    @include('shop.partials.icon', ['icon' => 'tag'])
                    Solo ofertas
                </button>

                @include('shop.partials.sort-sheet', ['sortOptions' => $sortOptions, 'sort' => $sort])
            </div>

            <div style="margin-top:1rem;">
                @if ($results === [])
                    <div class="sh-empty">
                        <span class="sh-empty__art" aria-hidden="true">
                            @include('shop.partials.icon', ['icon' => 'box'])
                        </span>
                        <h3>Sin productos por ahora</h3>
                        <p>Esta categoría no tiene existencia disponible en este momento.</p>
                        <a href="{{ route('shop.search') }}" wire:navigate class="sh-btn sh-btn--ghost" style="margin-top:0.6rem;">
                            Ver todo el catálogo
                        </a>
                    </div>
                @else
                    <div class="sh-grid">
                        @foreach ($results as $product)
                            @include('shop.partials.product-card', [
                                'product' => $product,
                                'cartQty' => (int) ($cartQuantities[$product['id']] ?? 0),
                                'priority' => $loop->index < 8,
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
