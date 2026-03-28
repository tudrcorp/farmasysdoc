<?php

namespace App\Filament\Resources\Inventories\Tables;

use App\Enums\ProductType;
use App\Filament\Exports\InventoryExporter;
use App\Filament\Resources\Inventories\InventoryResource;
use App\Models\Branch;
use App\Models\Inventory;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use League\Csv\Bom;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoriesTable
{
    /**
     * Mismo alcance que ventas: {@see BranchAuthScope} (ADMINISTRADOR o sucursal del usuario).
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['branch', 'product']))
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray'),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->description(fn (Inventory $record): string => self::formatProductMeta($record))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Inventory $record): string => $record->product?->name ?? '—')
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray'),
                TextColumn::make('product.barcode')
                    ->label('Código')
                    ->state(fn (Inventory $record): string => filled($record->product?->barcode)
                        ? (string) $record->product->barcode
                        : '000'.$record->product_id)
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->placeholder('—')
                    ->weight('medium')
                    ->toggleable(),
                TextColumn::make('active_ingredient')
                    ->label('Principio(s) activo(s)')
                    ->state(fn (Inventory $record): string => self::formatActiveIngredients($record))
                    ->tooltip(fn (Inventory $record): ?string => self::formatActiveIngredients($record, long: true))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_health')
                    ->label('Estado')
                    ->state(fn (Inventory $record): string => self::stockHealthLabel($record))
                    ->badge()
                    ->color(fn (Inventory $record): string => self::stockHealthColor($record)),
                TextColumn::make('quantity')
                    ->label('Existencias')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
                TextColumn::make('available_quantity')
                    ->label('Disponible')
                    ->state(fn (Inventory $record): float => (float) $record->available_quantity)
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
                TextColumn::make('reserved_quantity')
                    ->label('Reservado')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('reorder_point')
                    ->label('Reorden')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('minimum_stock')
                    ->label('Stock mínimo')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('maximum_stock')
                    ->label('Stock máximo')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('storage_location')
                    ->label('Ubicación')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(30)
                    ->tooltip(fn (Inventory $record): ?string => $record->storage_location)
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->toggleable(),
                IconColumn::make('allow_negative_stock')
                    ->label('Negativo')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (Inventory $record): string => $record->allow_negative_stock
                        ? 'Se permite saldo negativo en esta sucursal'
                        : 'No se permite saldo negativo'),
                TextColumn::make('last_movement_at')
                    ->label('Último movimiento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('last_stock_take_at')
                    ->label('Último conteo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin inventarios registrados')
            ->emptyStateDescription('Crea un registro de inventario para una sucursal y producto. Aquí verás niveles de stock, disponibilidad y alertas de reposición.')
            ->emptyStateIcon(Heroicon::ArchiveBox)
            ->recordUrl(fn (Inventory $record): string => InventoryResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => $record->name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('product_type')
                    ->label('Tipo de producto')
                    ->options(ProductType::options())
                    ->multiple()
                    ->searchable(),
                TernaryFilter::make('allow_negative_stock')
                    ->label('Permitir saldo negativo')
                    ->placeholder('Todos')
                    ->trueLabel('Solo permitidos')
                    ->falseLabel('Solo no permitidos'),
                TernaryFilter::make('low_stock')
                    ->label('Alerta de reposición')
                    ->placeholder('Todos')
                    ->trueLabel('Solo bajo mínimo/reorden')
                    ->falseLabel('Solo sobre mínimo/reorden')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereRaw('quantity <= COALESCE(reorder_point, minimum_stock, 0)'),
                        false: fn (Builder $query): Builder => $query->whereRaw('quantity > COALESCE(reorder_point, minimum_stock, 0)'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
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
                    BulkAction::make('exportCsv')
                        ->label('Exportar seleccionados')
                        ->icon(Heroicon::ArrowDownTray)
                        ->action(fn (Collection $records): StreamedResponse => self::streamSelectedInventoriesCsv($records)),
                ]),
            ]);
    }

    /**
     * Exportación CSV inmediata (sin cola): usa las columnas y formato de {@see InventoryExporter}.
     */
    private static function streamSelectedInventoriesCsv(Collection $records): StreamedResponse
    {
        $records->loadMissing(['branch', 'product']);

        $columns = InventoryExporter::getColumns();
        $columnMap = collect($columns)
            ->mapWithKeys(fn (ExportColumn $column): array => [
                $column->getName() => $column->getLabel() ?? $column->getName(),
            ])
            ->all();

        $export = new Export;
        $export->exporter = InventoryExporter::class;
        $export->file_disk = config('filament.default_filesystem_disk', 'local');

        $exporter = $export->getExporter($columnMap, []);

        $fileName = 'inventarios-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(
            function () use ($exporter, $records, $columnMap): void {
                $csv = Writer::from('php://output', 'w');
                $csv->setOutputBOM(Bom::Utf8);
                $csv->setDelimiter(InventoryExporter::getCsvDelimiter());
                $csv->insertOne(array_values($columnMap));

                foreach ($records as $record) {
                    $csv->insertOne($exporter($record));
                }
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    private static function stockHealthLabel(Inventory $record): string
    {
        $threshold = (float) ($record->reorder_point ?? $record->minimum_stock ?? 0);
        $quantity = (float) $record->quantity;

        if ($threshold > 0 && $quantity <= $threshold) {
            return 'Bajo stock';
        }

        return 'Estable';
    }

    private static function stockHealthColor(Inventory $record): string
    {
        $threshold = (float) ($record->reorder_point ?? $record->minimum_stock ?? 0);
        $quantity = (float) $record->quantity;

        if ($threshold > 0 && $quantity <= $threshold) {
            return 'danger';
        }

        return 'success';
    }

    private static function formatProductMeta(Inventory $record): string
    {
        $type = $record->product_type instanceof ProductType ? $record->product_type->label() : null;
        $presentation = $record->presentation_type;

        $parts = array_filter([$type, $presentation]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    private static function formatActiveIngredients(Inventory $record, bool $long = false): ?string
    {
        if (! is_array($record->active_ingredient) || $record->active_ingredient === []) {
            return null;
        }

        $ingredients = array_values(array_filter($record->active_ingredient, fn (mixed $value): bool => is_string($value) && filled($value)));

        if ($ingredients === []) {
            return null;
        }

        $text = implode(', ', $ingredients);

        if (! $long && mb_strlen($text) > 48) {
            return mb_substr($text, 0, 48).'...';
        }

        return $text;
    }
}
