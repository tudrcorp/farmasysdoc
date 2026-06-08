<?php

namespace App\Filament\Resources\InventoryStockFailures\Tables;

use App\Filament\Resources\InventoryStockFailures\InventoryStockFailureResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Branch;
use App\Models\InventoryStockFailure;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryStockFailuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('ID copiado')
                    ->tooltip('Identificador del registro'),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->description(fn (InventoryStockFailure $record): string => 'Cajero: '.($record->user?->name ?? '—')),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('product_name')
                    ->label('Producto')
                    ->description(fn (InventoryStockFailure $record): string => 'Código: '.($record->product_code !== '' ? $record->product_code : '—'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (InventoryStockFailure $record): string => $record->product_name)
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray')
                    ->url(fn (InventoryStockFailure $record): ?string => $record->product_id
                        ? ProductResource::getUrl('view', ['record' => $record->product_id], isAbsolute: false)
                        : null)
                    ->color('primary')
                    ->openUrlInNewTab(false),
                TextColumn::make('product_code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Existencia')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 3, ',', '.'))
                    ->badge()
                    ->color('danger')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->sortable()
                    ->tooltip('Existencia en sucursal al momento del intento en caja'),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->description(fn (InventoryStockFailure $record): string => (string) ($record->user?->email ?? '—'))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin fallas de existencia')
            ->emptyStateDescription('Cuando un cajero intente agregar un producto con existencia 0 en la caja registradora, el evento aparecerá aquí.')
            ->emptyStateIcon(Heroicon::ExclamationTriangle)
            ->recordUrl(fn (InventoryStockFailure $record): string => InventoryStockFailureResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => $record->name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')
                    ->label('Cajero')
                    ->relationship(
                        name: 'user',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereJsonContains('roles', 'CAJERO')
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name.' · '.$record->email)
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
}
