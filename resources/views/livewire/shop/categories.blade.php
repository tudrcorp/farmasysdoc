<div>
    @include('shop.partials.header', ['title' => 'Categorías', 'showCart' => true])

    <main class="sh-main">
        <div class="sh-page" style="padding-top:1rem;">
            @if ($categories === [])
                <div class="sh-empty">
                    <span class="sh-empty__art" aria-hidden="true">
                        @include('shop.partials.icon', ['icon' => 'grid'])
                    </span>
                    <h3>Categorías en camino</h3>
                    <p>Estamos organizando el catálogo. Vuelve en un momento.</p>
                </div>
            @else
                <div class="sh-sheet__group">
                    @foreach ($categories as $category)
                        <a
                            href="{{ $category['slug'] !== '' ? route('shop.category', $category['slug']) : route('shop.search', ['cat' => $category['id']]) }}"
                            wire:navigate.hover
                            class="sh-sheet__item"
                            wire:key="all-cat-{{ $category['id'] }}"
                        >
                            <span class="sh-cat__icon" style="width:2.6rem;height:2.6rem;border-radius:0.85rem;" aria-hidden="true">
                                @if ($category['image_url'])
                                    <img src="{{ $category['image_url'] }}" alt="" loading="lazy" decoding="async">
                                @else
                                    @include('shop.partials.category-glyph', ['category' => $category])
                                @endif
                            </span>
                            <span class="sh-sheet__copy">
                                <strong>{{ $category['name'] }}</strong>
                                <span>{{ $category['product_count'] }} productos</span>
                            </span>
                            <span class="sh-sheet__chevron" aria-hidden="true">
                                @include('shop.partials.icon', ['icon' => 'chevron'])
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>
