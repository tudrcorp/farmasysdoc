<?php

namespace App\Filament\Exports;

use App\Models\Inventory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class InventoryExporter extends Exporter
{
    protected static ?string $model = Inventory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('branch.name')
                ->label('Sucursal'),
            ExportColumn::make('product.name')
                ->label('Producto'),
            ExportColumn::make('product.sale_price')
                ->label('Precio venta lista (producto)'),
            ExportColumn::make('product.cost_price')
                ->label('Costo unitario (producto)'),
            ExportColumn::make('product.discount_percent')
                ->label('Descuento % (producto)'),
            ExportColumn::make('quantity')
                ->label('Cantidad'),
            ExportColumn::make('reserved_quantity')
                ->label('Cantidad reservada'),
            ExportColumn::make('reorder_point')
                ->label('Punto de pedido'),
            ExportColumn::make('minimum_stock')
                ->label('Stock mínimo'),
            ExportColumn::make('maximum_stock')
                ->label('Stock máximo'),
            ExportColumn::make('storage_location')
                ->label('Ubicación en almacén'),
            ExportColumn::make('allow_negative_stock')
                ->label('Permite stock negativo'),
            ExportColumn::make('last_movement_at')
                ->label('Último movimiento'),
            ExportColumn::make('last_stock_take_at')
                ->label('Último arqueo'),
            ExportColumn::make('notes')
                ->label('Notas'),
            ExportColumn::make('productCategory.name')
                ->label('Categoría (snapshot)'),
            ExportColumn::make('active_ingredient')
                ->label('Principio activo'),
            ExportColumn::make('concentration')
                ->label('Concentración'),
            ExportColumn::make('presentation_type')
                ->label('Tipo de presentación'),
            ExportColumn::make('created_by')
                ->label('Creado por'),
            ExportColumn::make('updated_by')
                ->label('Actualizado por'),
            ExportColumn::make('created_at')
                ->label('Fecha de creación'),
            ExportColumn::make('updated_at')
                ->label('Fecha de actualización'),
        ];
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Exportación de inventario completada';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $successful = Number::format($export->successful_rows);
        $successfulLabel = $export->successful_rows === 1
            ? 'fila exportada'
            : 'filas exportadas';
        $body = "La exportación finalizó: {$successful} {$successfulLabel}.";

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $failed = Number::format($failedRowsCount);
            $failedLabel = $failedRowsCount === 1
                ? 'fila no pudo exportarse'
                : 'filas no pudieron exportarse';
            $body .= " {$failed} {$failedLabel}.";
        }

        return $body;
    }
}
