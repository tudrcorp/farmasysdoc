<?php

namespace App\Filament\Resources\InventoryAdjustments\Pages;

use App\Filament\Resources\InventoryAdjustments\Actions\ApplyInventoryAdjustmentAction;
use App\Filament\Resources\InventoryAdjustments\Actions\QuickCreateInventoryAdjustmentProductAction;
use App\Filament\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Filament\Resources\InventoryAdjustments\Pages\Concerns\InteractsWithInventoryAdjustmentQuickCreate;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListInventoryAdjustments extends ListRecords
{
    use InteractsWithInventoryAdjustmentQuickCreate;

    protected static string $resource = InventoryAdjustmentResource::class;

    protected static ?string $title = 'Ajustes de inventario';

    public function mount(): void
    {
        parent::mount();

        $this->js(ApplyInventoryAdjustmentAction::registerQuickCreateEnterListenerJs());
    }

    public function getHeading(): string|Htmlable
    {
        return static::$title ?? 'Ajustes de inventario';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Consulta deltas de stock por anulación de compras y otros motivos. Filtros por sucursal, motivo o rango de fechas.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ApplyInventoryAdjustmentAction::make(),
            QuickCreateInventoryAdjustmentProductAction::make(function (
                Product $product,
                ?float $unitCostFromModal,
                string $repeaterKey,
            ): void {
                $this->assignCreatedProductToAdjustmentLine(
                    $product,
                    $unitCostFromModal,
                    $repeaterKey,
                );
            }),
        ];
    }
}
