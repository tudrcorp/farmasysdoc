<?php

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('supplier.id')
                ->label('ID proveedor'),
            ExportColumn::make('barcode')
                ->label('Código de barras'),
            ExportColumn::make('name')
                ->label('Nombre'),
            ExportColumn::make('slug')
                ->label('Slug'),
            ExportColumn::make('description')
                ->label('Descripción'),
            ExportColumn::make('product_type')
                ->label('Tipo de producto'),
            ExportColumn::make('brand')
                ->label('Marca'),
            ExportColumn::make('presentation')
                ->label('Presentación'),
            ExportColumn::make('unit_of_measure')
                ->label('Unidad de medida'),
            ExportColumn::make('unit_content')
                ->label('Contenido unitario'),
            ExportColumn::make('net_content_label')
                ->label('Etiqueta de contenido neto'),
            ExportColumn::make('sale_price')
                ->label('Precio de venta'),
            ExportColumn::make('cost_price')
                ->label('Precio de costo'),
            ExportColumn::make('tax_rate')
                ->label('Tasa de impuesto (%)'),
            ExportColumn::make('active_ingredient')
                ->label('Principio activo'),
            ExportColumn::make('concentration')
                ->label('Concentración'),
            ExportColumn::make('presentation_type')
                ->label('Tipo de presentación'),
            ExportColumn::make('requires_prescription')
                ->label('Requiere receta'),
            ExportColumn::make('is_controlled_substance')
                ->label('Sustancia controlada'),
            ExportColumn::make('health_registration_number')
                ->label('Registro sanitario'),
            ExportColumn::make('ingredients')
                ->label('Ingredientes'),
            ExportColumn::make('allergens')
                ->label('Alérgenos'),
            ExportColumn::make('nutritional_information')
                ->label('Información nutricional'),
            ExportColumn::make('manufacturer')
                ->label('Fabricante'),
            ExportColumn::make('model')
                ->label('Modelo'),
            ExportColumn::make('warranty_months')
                ->label('Garantía (meses)'),
            ExportColumn::make('medical_device_class')
                ->label('Clase de dispositivo médico'),
            ExportColumn::make('requires_calibration')
                ->label('Requiere calibración'),
            ExportColumn::make('storage_conditions')
                ->label('Condiciones de almacenamiento'),
            ExportColumn::make('is_active')
                ->label('Activo'),
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
        return 'Exportación de productos completada';
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
