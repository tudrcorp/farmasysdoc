<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\Actions\CashRegisterAction;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Sales\Widgets\StatsListSaleOverview;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSales extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Listado de Ventas';

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            StatsListSaleOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['@sm' => 1, '@md' => 2, '@lg' => 4];
    }

    /**
     * Registra la acción de caja (carrito) en caché sin mostrarla en la cabecera.
     * No usar ->hidden() en esa acción: en Filament las acciones ocultas se tratan como deshabilitadas
     * y no se pueden montar con replaceMountedAction / mountAction.
     */
    public function cacheInteractsWithHeaderActions(): void
    {
        parent::cacheInteractsWithHeaderActions();

        $this->cacheAction(CashRegisterAction::makeRegister());
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Venta Directa')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
            CashRegisterAction::makeClientGate(),
        ];
    }
}
