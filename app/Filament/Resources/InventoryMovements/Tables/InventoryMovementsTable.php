<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use App\Enums\InventoryMovementType;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\Branch;
use App\Models\InventoryMovement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['product', 'inventory.branch']))
            ->columns([
                TextColumn::make('id')
                    ->label('Movimiento')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('ID copiado'),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->description(fn (InventoryMovement $record): string => self::formatMovementContext($record))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (InventoryMovement $record): string => $record->product?->name ?? '—')
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray'),
                TextColumn::make('inventory_id')
                    ->label('Inv.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('movement_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => InventoryMovementType::tryLabel($state))
                    ->color(fn (?InventoryMovementType $state): string => self::movementTypeColor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(0)
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium')
                    ->color(fn (InventoryMovement $record): string => self::quantityColor($record->movement_type)),
                TextColumn::make('unit_cost')
                    ->label('Costo unit.')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('batch_number')
                    ->label('Lote')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Lote copiado')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expiry_date')
                    ->label('Vence')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (InventoryMovement $record): string => self::expiryColor($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_type')
                    ->label('Ref. tipo')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_id')
                    ->label('Ref. ID')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Usuario')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin movimientos de inventario')
            ->emptyStateDescription('Registra entradas, salidas, ajustes y transferencias para mantener trazabilidad del inventario por producto y sucursal.')
            ->emptyStateIcon(Heroicon::ArrowPath)
            ->recordUrl(fn (InventoryMovement $record): string => InventoryMovementResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('Tipo de movimiento')
                    ->options(InventoryMovementType::options())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'inventory.branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => $record->name)
                    ->searchable()
                    ->preload(),
                Filter::make('created_between')
                    ->label('Fecha de registro')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Desde'),
                        DatePicker::make('created_until')
                            ->label('Hasta'),
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

    private static function movementTypeColor(?InventoryMovementType $state): string
    {
        return match ($state) {
            InventoryMovementType::Purchase, InventoryMovementType::Initial, InventoryMovementType::Return => 'success',
            InventoryMovementType::Sale, InventoryMovementType::Loss, InventoryMovementType::Damage => 'danger',
            InventoryMovementType::Adjustment, InventoryMovementType::Transfer, InventoryMovementType::StockTake => 'warning',
            default => 'gray',
        };
    }

    private static function quantityColor(?InventoryMovementType $type): string
    {
        return match ($type) {
            InventoryMovementType::Purchase, InventoryMovementType::Initial, InventoryMovementType::Return => 'success',
            InventoryMovementType::Sale, InventoryMovementType::Loss, InventoryMovementType::Damage => 'danger',
            default => 'gray',
        };
    }

    private static function expiryColor(InventoryMovement $record): string
    {
        if ($record->expiry_date === null) {
            return 'gray';
        }

        if ($record->expiry_date->isPast()) {
            return 'danger';
        }

        if ($record->expiry_date->diffInDays(now()) <= 30) {
            return 'warning';
        }

        return 'success';
    }

    private static function formatMovementContext(InventoryMovement $record): string
    {
        $branch = $record->inventory?->branch?->name;
        $inventoryId = $record->inventory_id !== null ? 'Inv. #'.$record->inventory_id : null;

        $parts = array_filter([$branch, $inventoryId]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }
}
