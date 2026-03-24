<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductType;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('supplier'))
            ->columns([
                TextColumn::make('barcode')
                    ->label('Codigo')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->placeholder('—')
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label('Nombre comercial')
                    ->description(fn (Product $record): ?string => self::formatNameDescription($record))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Product $record): string => $record->name)
                    ->icon(Heroicon::ShoppingBag)
                    ->iconColor('gray'),
                IconColumn::make('is_active')
                    ->label('Catálogo')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter()
                    ->tooltip(fn (Product $record): string => $record->is_active
                        ? 'Activo: visible para ventas y catálogo'
                        : 'Inactivo: puede ocultarse en ventas'),
                TextColumn::make('supplier_label')
                    ->label('Proveedor')
                    ->state(fn (Product $record): string => self::formatSupplierLabel($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('supplier', function (Builder $q) use ($search): void {
                            $q->where('legal_name', 'like', "%{$search}%")
                                ->orWhere('trade_name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->limit(36)
                    ->tooltip(fn (Product $record): string => self::formatSupplierLabel($record))
                    ->placeholder('—')
                    ->icon(Heroicon::Truck)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('product_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?ProductType $state): string => $state instanceof ProductType ? $state->label() : '—')
                    ->color(fn (?ProductType $state): string => match ($state) {
                        ProductType::Medication => 'danger',
                        ProductType::MedicalEquipment => 'info',
                        ProductType::Food => 'success',
                        ProductType::Perfumery, ProductType::PersonalHygiene => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sale_price')
                    ->label('Precio venta')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
                TextColumn::make('cost_price')
                    ->label('Costo')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_rate')
                    ->label('IVA / impuesto')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 2, ',', '.').' %' : '—')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('barcode')
                    ->label('Código de barras')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::QrCode)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn (Product $record): ?string => filled($record->slug) ? $record->slug : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('active_ingredient')
                    ->label('Principio activo')
                    ->limit(40)
                    ->tooltip(fn (Product $record): ?string => filled($record->active_ingredient) ? $record->active_ingredient : null)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brand')
                    ->label('Marca / laboratorio')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('presentation')
                    ->label('Presentación')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit_of_measure')
                    ->label('Unidad de venta')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit_content')
                    ->label('Contenido por unidad')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('net_content_label')
                    ->label('Etiqueta contenido')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('concentration')
                    ->label('Concentración')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('presentation_type')
                    ->label('Forma farmacéutica')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('requires_prescription')
                    ->label('Receta')
                    ->boolean()
                    ->trueIcon(Heroicon::DocumentText)
                    ->falseIcon(Heroicon::MinusSmall)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (Product $record): string => $record->requires_prescription
                        ? 'Requiere fórmula médica'
                        : 'Venta sin receta obligatoria'),
                IconColumn::make('is_controlled_substance')
                    ->label('Controlado')
                    ->boolean()
                    ->trueIcon(Heroicon::ShieldExclamation)
                    ->falseIcon(Heroicon::MinusSmall)
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (Product $record): string => $record->is_controlled_substance
                        ? 'Sustancia controlada / psicotrópico'
                        : 'No es sustancia controlada'),
                TextColumn::make('health_registration_number')
                    ->label('Registro sanitario')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warranty_months')
                    ->label('Garantía (meses)')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('medical_device_class')
                    ->label('Clase dispositivo')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('requires_calibration')
                    ->label('Calibración')
                    ->boolean()
                    ->trueIcon(Heroicon::WrenchScrewdriver)
                    ->falseIcon(Heroicon::MinusSmall)
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (Product $record): string => $record->requires_calibration
                        ? 'Requiere calibración periódica'
                        : 'Sin calibración obligatoria'),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin productos en el catálogo')
            ->emptyStateDescription('Crea un producto para registrar SKU, precios, tipo y datos regulatorios. Usa el botón «Crear» del encabezado.')
            ->emptyStateIcon(Heroicon::Cube)
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado en catálogo')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
                TernaryFilter::make('requires_prescription')
                    ->label('Fórmula médica')
                    ->placeholder('Todos')
                    ->trueLabel('Con receta obligatoria')
                    ->falseLabel('Sin receta obligatoria'),
                TernaryFilter::make('is_controlled_substance')
                    ->label('Control farmacéutico')
                    ->placeholder('Todos')
                    ->trueLabel('Solo controlados')
                    ->falseLabel('Sin control especial'),
                SelectFilter::make('product_type')
                    ->label('Tipo de producto')
                    ->options(ProductType::options())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship(
                        name: 'supplier',
                        titleAttribute: 'legal_name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('legal_name'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Supplier $record): string => $record->trade_name ?: $record->legal_name,
                    )
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver ficha')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    private static function formatNameDescription(Product $product): ?string
    {
        $parts = array_filter([$product->brand, $product->presentation]);

        return $parts ? implode(' · ', $parts) : null;
    }

    private static function formatSupplierLabel(Product $product): string
    {
        $supplier = $product->supplier;
        if (! $supplier) {
            return '—';
        }

        return $supplier->trade_name ?: $supplier->legal_name ?: '—';
    }
}
