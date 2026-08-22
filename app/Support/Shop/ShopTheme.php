<?php

namespace App\Support\Shop;

/**
 * Tema claro/oscuro de la PWA. El cliente lo guarda en cookie + localStorage
 * para que cada navegación Livewire pinte el <html> correcto.
 */
final class ShopTheme
{
    public const COOKIE = 'fd-shop-theme';

    public static function current(): string
    {
        $value = request()->cookie(self::COOKIE);

        return $value === 'dark' ? 'dark' : 'light';
    }

    public static function isDark(): bool
    {
        return self::current() === 'dark';
    }
}
