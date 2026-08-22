@props(['category'])

@php
    $name = (string) ($category['name'] ?? 'Categoría');
    $key = mb_strtolower($name);
    $glyph = match (true) {
        str_contains($key, 'bebe') || str_contains($key, 'bebé') || str_contains($key, 'baby') || str_contains($key, 'mater') => 'baby',
        str_contains($key, 'vitamin') || str_contains($key, 'nutri') || str_contains($key, 'suplement') => 'vitamin',
        str_contains($key, 'cuidado') || str_contains($key, 'piel') || str_contains($key, 'derm') || str_contains($key, 'personal') => 'sparkle',
        str_contains($key, 'disposit') || str_contains($key, 'equip') => 'device',
        str_contains($key, 'salud') || str_contains($key, 'bienestar') => 'heart',
        str_contains($key, 'medic') || str_contains($key, 'farm') || ($category['is_medication'] ?? false) => 'pill',
        default => 'box',
    };
    $search = $name;
    $categoryId = (int) ($category['id'] ?? 0);
    $slug = (string) ($category['slug'] ?? '');
    $isOffers = $slug === 'ofertas' || mb_strtolower($name) === 'ofertas';
    $count = (int) ($category['product_count'] ?? 0);
@endphp

<button
    type="button"
    class="fd-cat fd-glass"
    data-category-search="{{ $search }}"
    @if ($categoryId > 0) data-category-id="{{ $categoryId }}" @endif
    @if ($isOffers) data-category-offers="1" @endif
>
    <span class="fd-cat__icon" aria-hidden="true">
        @if (! empty($category['image_url']))
            <img src="{{ $category['image_url'] }}" alt="">
        @elseif ($glyph === 'pill')
            <svg viewBox="0 0 64 64" fill="none"><rect x="14" y="22" width="36" height="20" rx="10" fill="#18ACB2"/><path d="M32 22v20" stroke="#fff" stroke-width="3"/><rect x="14" y="22" width="18" height="20" rx="10" fill="#FCE422"/></svg>
        @elseif ($glyph === 'heart')
            <svg viewBox="0 0 64 64" fill="none"><path d="M32 52s-18-11-18-24a10 10 0 0 1 18-8 10 10 0 0 1 18 8c0 13-18 24-18 24Z" fill="#18ACB2"/></svg>
        @elseif ($glyph === 'sparkle')
            <svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="32" r="14" fill="#18ACB2"/><path d="M32 10v8M32 46v8M10 32h8M46 32h8" stroke="#FCE422" stroke-width="4" stroke-linecap="round"/></svg>
        @elseif ($glyph === 'device')
            <svg viewBox="0 0 64 64" fill="none"><rect x="18" y="12" width="28" height="40" rx="6" fill="#0E949A"/><rect x="22" y="18" width="20" height="24" rx="2" fill="#E7F7F8"/><circle cx="32" cy="48" r="2.5" fill="#FCE422"/></svg>
        @elseif ($glyph === 'baby')
            <svg viewBox="0 0 64 64" fill="none"><circle cx="32" cy="28" r="14" fill="#18ACB2"/><circle cx="27" cy="26" r="2" fill="#fff"/><circle cx="37" cy="26" r="2" fill="#fff"/><path d="M27 34c2 3 8 3 10 0" stroke="#fff" stroke-width="2" stroke-linecap="round"/><path d="M18 48c4-8 24-8 28 0" stroke="#0E949A" stroke-width="4" stroke-linecap="round"/></svg>
        @elseif ($glyph === 'vitamin')
            <svg viewBox="0 0 64 64" fill="none"><rect x="20" y="10" width="24" height="44" rx="12" fill="#0E949A"/><path d="M20 32h24" stroke="#FCE422" stroke-width="3"/><circle cx="32" cy="22" r="4" fill="#fff"/></svg>
        @else
            <svg viewBox="0 0 64 64" fill="none"><rect x="14" y="18" width="36" height="28" rx="8" fill="#18ACB2"/><path d="M14 28h36" stroke="#FCE422" stroke-width="3"/><path d="M32 18v28" stroke="#fff" stroke-width="2"/></svg>
        @endif
    </span>
    <strong>{{ $name }}</strong>
    @if ($count > 0)
        <span class="fd-product-card__meta">{{ $count }} productos</span>
    @endif
</button>
