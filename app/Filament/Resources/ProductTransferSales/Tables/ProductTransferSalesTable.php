<?php

namespace App\Filament\Resources\ProductTransferSales\Tables;

use App\Enums\ProductTransferStatus;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Branch;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\Audit\ProductTransferSaleAuditLogger;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

class ProductTransferSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes([
                'class' => 'fi-ta-product-transfer-sales-table',
            ], merge: true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'items',
                'fromBranch',
                'toBranch',
                'deliveryUser',
                'sale',
                'client',
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->weight('medium')
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->url(fn (ProductTransfer $record): ?string => ProductTransferStatus::isInProgressValue($record->status)
                        ? SaleResource::getUrl('index', [
                            'abrir' => 'caja',
                            'traslado_venta' => $record->id,
                        ], isAbsolute: false)
                        : null)
                    ->tooltip(fn (ProductTransfer $record): string => ProductTransferStatus::isInProgressValue($record->status)
                        ? 'Abrir caja con este traslado precargado'
                        : 'Identificador único del traslado'),
                TextColumn::make('sale.sale_number')
                    ->label('Venta')
                    ->description(fn (ProductTransfer $record): ?string => filled($record->client?->name)
                        ? $record->client->name
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('sale', function (Builder $q) use ($search): void {
                            $q->where('sale_number', 'like', '%'.$search.'%');
                        });
                    })
                    ->sortable()
                    ->placeholder('—')
                    ->weight('medium')
                    ->icon(Heroicon::ShoppingBag)
                    ->iconColor('primary')
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Líneas')
                    ->sortable()
                    ->numeric()
                    ->alignment(Alignment::Center)
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray')
                    ->tooltip('Cantidad de productos en el traslado'),
                TextColumn::make('fromBranch.name')
                    ->label('Origen')
                    ->description(fn (ProductTransfer $record): ?string => filled($record->fromBranch?->code)
                        ? 'Cód. '.$record->fromBranch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('fromBranch', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%');
                        });
                    })
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('toBranch.name')
                    ->label('Destino')
                    ->description(fn (ProductTransfer $record): ?string => filled($record->toBranch?->code)
                        ? 'Cód. '.$record->toBranch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('toBranch', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%');
                        });
                    })
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('total_transfer_cost')
                    ->label('Costo envío')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ProductTransferStatus::labelForStored(
                        $state instanceof ProductTransferStatus ? $state : (filled($state) ? (string) $state : null),
                    ))
                    ->color(fn (mixed $state): string => ProductTransferStatus::filamentColorForStored(
                        $state instanceof ProductTransferStatus ? $state : (filled($state) ? (string) $state : null),
                    ))
                    ->searchable()
                    ->sortable()
                    ->alignment(Alignment::Center)
                    ->icon(Heroicon::Signal)
                    ->tooltip('Flujo: Pendiente → En proceso → Completado'),
                TextColumn::make('deliveryUser.name')
                    ->label('Delivery')
                    ->description(fn (ProductTransfer $record): ?string => filled($record->deliveryUser?->email)
                        ? (string) $record->deliveryUser->email
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('deliveryUser', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                    })
                    ->sortable()
                    ->placeholder('Sin asignar')
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('delivery_entrega')
                    ->label('Datos de entrega')
                    ->state(function (ProductTransfer $record): string {
                        $parts = array_values(array_filter([
                            filled($record->delivery_recipient_name) ? (string) $record->delivery_recipient_name : null,
                            filled($record->delivery_recipient_phone) ? (string) $record->delivery_recipient_phone : null,
                            filled($record->delivery_address)
                                ? Str::limit((string) $record->delivery_address, 42)
                                : null,
                        ]));

                        return $parts !== [] ? implode(' · ', $parts) : '—';
                    })
                    ->wrap()
                    ->lineClamp(2)
                    ->icon(Heroicon::Truck)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (ProductTransfer $record): ?string => filled($record->delivery_notes)
                        ? (string) $record->delivery_notes
                        : null),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray'),
                TextColumn::make('in_progress_at')
                    ->label('En proceso desde')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->label('Completado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->emptyStateHeading('Sin traslados de venta')
            ->emptyStateDescription('Cuando una sucursal solicite mercancía ligada a una venta para entrega al cliente, el pedido aparecerá aquí. Delivery y sucursales involucradas pueden hacer seguimiento hasta completar el envío.')
            ->emptyStateIcon(Heroicon::Truck)
            ->recordUrl(fn (ProductTransfer $record): string => ProductTransferSaleResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(ProductTransferStatus::options())
                    ->multiple()
                    ->native(false),
                SelectFilter::make('from_branch_id')
                    ->label('Sucursal origen')
                    ->relationship(
                        name: 'fromBranch',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $query->where('is_active', true)->orderBy('name');

                            return BranchAuthScope::applyToBranchFormSelect($query);
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => filled($record->code)
                        ? $record->name.' ('.$record->code.')'
                        : $record->name)
                    ->searchable()
                    ->preload()
                    ->native(false),
                SelectFilter::make('to_branch_id')
                    ->label('Sucursal destino')
                    ->relationship(
                        name: 'toBranch',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $query->where('is_active', true)->orderBy('name');

                            return BranchAuthScope::applyToBranchFormSelect($query);
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => filled($record->code)
                        ? $record->name.' ('.$record->code.')'
                        : $record->name)
                    ->searchable()
                    ->preload()
                    ->native(false),
                SelectFilter::make('delivery_user_id')
                    ->label('Delivery asignado')
                    ->relationship(
                        name: 'deliveryUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),
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
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver traslado de venta')
                        ->icon(Heroicon::Eye),
                    ProductTransferResource::takeTransferAction(),
                    ProductTransferResource::markCompletedAction(),
                    ProductTransferResource::adminChangeStatusAction(),
                ]),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->isAdministrator())
                        ->using(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void {
                            $isFirstException = true;
                            $records->each(function (Model $record) use ($action, &$isFirstException): void {
                                try {
                                    if ($record instanceof ProductTransfer && ProductTransferSaleAuditLogger::isSaleTransfer($record)) {
                                        ProductTransferSaleAuditLogger::logDeleted($record);
                                    }

                                    $record->delete() || $action->reportBulkProcessingFailure();
                                } catch (Throwable $exception) {
                                    $action->reportBulkProcessingFailure();

                                    if ($isFirstException) {
                                        report($exception);
                                        $isFirstException = false;
                                    }
                                }
                            });
                        }),
                ]),
            ]);
    }
}
