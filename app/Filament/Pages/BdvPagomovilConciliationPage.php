<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithBdvPagomovilConciliationForm;
use App\Filament\Resources\ConciliationBdvs\ConciliationBdvResource;
use App\Models\User;
use App\Services\BdvConciliation\BdvPagomovilConciliationService;
use App\Support\Sales\PosPaymentMethodOptions;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Conciliación manual de Pagomóvil BDV para usuarios autorizados (no es el laboratorio de APIs).
 */
final class BdvPagomovilConciliationPage extends Page
{
    use InteractsWithBdvPagomovilConciliationForm;

    protected static ?string $title = 'Conciliar Pagomóvil BDV';

    protected static ?string $navigationLabel = 'Conciliaciones BDV';

    protected static string|UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'conciliacion-pagomovil-bdv';

    protected string $view = 'filament.pages.bdv-pagomovil-conciliation';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('bdv_pagomovil_conciliation');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return PosPaymentMethodOptions::bdvNavigationIconUrl();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Conciliar Pagomóvil BDV';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Valida un pago recibido por Pago Móvil contra el Banco de Venezuela (getMovement/v2). Código 1000 = conciliado.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'canViewHistory' => ConciliationBdvResource::canViewAny(),
            'conciliationsUrl' => ConciliationBdvResource::getUrl('index'),
        ];
    }

    public function mount(BdvPagomovilConciliationService $service): void
    {
        abort_unless(self::canAccess(), 403);

        $this->bootBdvPagomovilConciliationForm($service);
    }

    public function resetForm(BdvPagomovilConciliationService $service): void
    {
        $this->resetBdvPagomovilConciliationForm($service);
    }
}
