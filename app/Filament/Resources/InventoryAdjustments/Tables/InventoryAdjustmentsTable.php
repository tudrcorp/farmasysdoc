<?php

namespace App\Filament\Resources\InventoryAdjustments\Tables;

use App\Filament\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\Branch;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Support\Filament\BranchAuthScope;
use App\Support\Inventory\InventoryAdjustmentReason;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryAdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['purchase', 'branch', 'product', 'inventoryMovement']))
            ->columns([
                TextColumn::make('id')
                    ->label('Ajuste')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('ID copiado')
                    ->tooltip('Identificador interno del ajuste'),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->description(fn (InventoryAdjustment $record): string => 'Por: '.(filled($record->created_by) ? (string) $record->created_by : '—')),
                TextColumn::make('purchase.purchase_number')
                    ->label('Compra')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->placeholder('—')
                    ->icon(Heroicon::ShoppingCart)
                    ->iconColor('gray')
                    ->url(fn (InventoryAdjustment $record): ?string => $record->purchase_id
                        ? route('purchases.document-pdf', ['purchase' => $record->purchase_id])
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->description(fn (InventoryAdjustment $record): string => self::productSecondaryLine($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = '%'.addcslashes($search, '%_\\').'%';
                        $query->whereHas('product', function (Builder $q) use ($like): void {
                            $q->where('name', 'like', $like)
                                ->orWhere('sku', 'like', $like)
                                ->orWhere('barcode', 'like', $like);
                        });
                    })
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (InventoryAdjustment $record): string => (string) ($record->product?->name ?? '—'))
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray'),
                TextColumn::make('quantity_delta')
                    ->label('Cantidad')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(function (mixed $state): string {
                        $n = (float) $state;
                        $sign = $n > 0 ? '+' : '';

                        return $sign.number_format($n, 3, ',', '.');
                    })
                    ->color(fn (InventoryAdjustment $record): string => self::deltaColor($record))
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->iconColor(fn (InventoryAdjustment $record): string => self::deltaColor($record))
                    ->sortable()
                    ->tooltip('Delta aplicado al inventario (negativo reduce existencias).'),
                TextColumn::make('unit_cost_snapshot')
                    ->label('Costo u.')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('gray'),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InventoryAdjustmentReason::label($state))
                    ->color(fn (?string $state): string => InventoryAdjustmentReason::filamentColor($state)),
                TextColumn::make('inventory_movement_id')
                    ->label('Mov.')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('—')
                    ->url(fn (InventoryAdjustment $record): ?string => $record->inventory_movement_id
                        ? InventoryMovementResource::getUrl('view', ['record' => $record->inventory_movement_id], isAbsolute: false)
                        : null)
                    ->openUrlInNewTab(false)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(36)
                    ->tooltip(fn (InventoryAdjustment $record): ?string => filled($record->notes) ? (string) $record->notes : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin ajustes de inventario')
            ->emptyStateDescription('Aquí aparecerán los ajustes generados al anular compras u otros procesos que muevan stock de forma controlada.')
            ->emptyStateIcon(Heroicon::AdjustmentsHorizontal)
            ->recordUrl(fn (InventoryAdjustment $record): string => InventoryAdjustmentResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('reason')
                    ->label('Motivo')
                    ->options(InventoryAdjustmentReason::options())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $query->where('is_active', true)->orderBy('name');

                            return BranchAuthScope::applyToBranchFormSelect($query);
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => $record->name)
                    ->searchable()
                    ->preload(),
                Filter::make('created_between')
                    ->label('Fecha de registro')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('created_until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['created_from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '>=', (string) $data['created_from']),
                            )
                            ->when(
                                filled($data['created_until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '<=', (string) $data['created_until']),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon(Heroicon::Eye),
            ])
            ->recordActionsColumnLabel('Acciones');
    }

    private static function productSecondaryLine(InventoryAdjustment $record): string
    {
        $product = $record->product;
        if (! $product instanceof Product) {
            return '—';
        }

        $parts = array_filter([
            filled($product->sku) ? 'SKU: '.$product->sku : null,
            filled($product->barcode) ? 'Cód: '.$product->barcode : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    private static function deltaColor(InventoryAdjustment $record): string
    {
        $n = (float) $record->quantity_delta;
        if ($n < -0.0001) {
            return 'danger';
        }
        if ($n > 0.0001) {
            return 'success';
        }

        return 'gray';
    }
}
