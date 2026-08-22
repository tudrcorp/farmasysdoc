@php
    /**
     * Ilustración por categoría, deducida del nombre cuando no hay imagen cargada.
     * Misma heurística que la tarjeta de categoría del storefront público.
     */
    $name = mb_strtolower((string) ($category['name'] ?? ''));

    $glyph = match (true) {
        str_contains($name, 'bebe') || str_contains($name, 'bebé') || str_contains($name, 'mater') => 'baby',
        str_contains($name, 'vitamin') || str_contains($name, 'nutri') || str_contains($name, 'suplement') => 'vitamin',
        str_contains($name, 'cuidado') || str_contains($name, 'piel') || str_contains($name, 'derm') || str_contains($name, 'personal') => 'skin',
        str_contains($name, 'disposit') || str_contains($name, 'equip') => 'device',
        str_contains($name, 'salud') || str_contains($name, 'bienestar') => 'heart',
        str_contains($name, 'medic') || str_contains($name, 'farm') || ($category['is_medication'] ?? false) => 'pill',
        default => 'box',
    };
@endphp

@switch($glyph)
    @case('pill')
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><rect x="12" y="22" width="40" height="20" rx="10" fill="#18ACB2"/><path d="M32 22v20" stroke="#fff" stroke-width="3"/><rect x="12" y="22" width="20" height="20" rx="10" fill="#FCE422"/></svg>
        @break

    @case('heart')
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><path d="M32 52s-18-11-18-24a10 10 0 0 1 18-8 10 10 0 0 1 18 8c0 13-18 24-18 24Z" fill="#18ACB2"/><path d="M20 30h6l3-6 5 12 3-6h7" stroke="#FCE422" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break

    @case('skin')
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><circle cx="32" cy="32" r="14" fill="#18ACB2"/><path d="M32 10v8M32 46v8M10 32h8M46 32h8" stroke="#FCE422" stroke-width="4" stroke-linecap="round"/></svg>
        @break

    @case('device')
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><rect x="18" y="12" width="28" height="40" rx="6" fill="#0E949A"/><rect x="22" y="18" width="20" height="24" rx="2" fill="#E7F7F8"/><circle cx="32" cy="48" r="2.5" fill="#FCE422"/></svg>
        @break

    @case('baby')
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><circle cx="32" cy="28" r="14" fill="#18ACB2"/><circle cx="27" cy="26" r="2" fill="#fff"/><circle cx="37" cy="26" r="2" fill="#fff"/><path d="M27 34c2 3 8 3 10 0" stroke="#fff" stroke-width="2" stroke-linecap="round"/><path d="M18 48c4-8 24-8 28 0" stroke="#FCE422" stroke-width="4" stroke-linecap="round"/></svg>
        @break

    @case('vitamin')
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><rect x="20" y="10" width="24" height="44" rx="12" fill="#0E949A"/><path d="M20 32h24" stroke="#FCE422" stroke-width="3"/><circle cx="32" cy="22" r="4" fill="#fff"/></svg>
        @break

    @default
        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><rect x="12" y="18" width="40" height="30" rx="8" fill="#18ACB2"/><path d="M12 28h40" stroke="#FCE422" stroke-width="3"/><path d="M32 18v30" stroke="#fff" stroke-width="2"/></svg>
@endswitch
