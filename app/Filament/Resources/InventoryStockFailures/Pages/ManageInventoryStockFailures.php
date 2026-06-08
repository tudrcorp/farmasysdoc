<?php

namespace App\Filament\Resources\InventoryStockFailures\Pages;

use App\Filament\Resources\InventoryStockFailures\InventoryStockFailureResource;
use App\Filament\Resources\InventoryStockFailures\Widgets\InventoryStockFailuresByProductChart;
use App\Services\Inventory\InventoryStockFailureCsvExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageInventoryStockFailures extends ManageRecords
{
    protected static string $resource = InventoryStockFailureResource::class;

    protected static ?string $title = 'Fallas de existencia';

    public function getHeading(): string|Htmlable
    {
        return static::$title ?? 'Fallas de existencia';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Productos escaneados en caja con existencia cero. Revise sucursal, código y cajero para reponer stock o corregir inventario.';
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            InventoryStockFailuresByProductChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Descargar CSV')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->tooltip('Exporta los registros visibles según los filtros aplicados en la tabla')
                ->action(fn (): StreamedResponse => app(InventoryStockFailureCsvExporter::class)
                    ->stream($this->getTableQueryForExport()))
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]),
        ];
    }
}
