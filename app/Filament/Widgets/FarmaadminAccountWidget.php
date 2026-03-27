<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Tarjeta de bienvenida en el sidebar. Estilos glass iOS: `theme.css` → `.fi-farmaadmin-account-widget`.
 */
class FarmaadminAccountWidget extends Widget
{
    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.farmaadmin.widgets.ios-account-widget';

    public static function canView(): bool
    {
        return Filament::auth()->check();
    }

    /**
     * @return array{user: ?Authenticatable}
     */
    protected function getViewData(): array
    {
        return [
            'user' => Filament::auth()->user(),
        ];
    }
}
