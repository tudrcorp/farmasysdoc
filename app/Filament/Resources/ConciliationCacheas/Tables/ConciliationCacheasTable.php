<?php

namespace App\Filament\Resources\ConciliationCacheas\Tables;

use App\Enums\ConciliationCacheaCollectionStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\ConciliationCacheas\ConciliationCacheaResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\ConciliationCachea;
use App\Models\User;
use App\Services\Sales\CacheaConciliationCollectionStatusService;
use App\Support\Filament\BranchAuthScope;
use App\Support\Sales\CacheaPosPaymentSupport;
use App\Support\Sales\PosPaymentMethodOptions;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ConciliationCacheasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['branch', 'user', 'sale']))
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Venta')
                    ->html()
                    ->formatStateUsing(fn (ConciliationCachea $record): string => self::formatSaleCell($record))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->tooltip('Abrir detalle de la conciliación'),
                TextColumn::make('recorded_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ConciliationCachea $record): string => $record->recorded_at?->diffForHumans() ?? '—')
                    ->sortable()
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color(fn (ConciliationCachea $record): string => self::branchBadgeColor($record->branch_id))
                    ->description(fn (ConciliationCachea $record): ?string => filled($record->branch?->code)
                        ? 'Código: '.$record->branch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('branch', function (Builder $branchQuery) use ($search): void {
                            $branchQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->url(fn (ConciliationCachea $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null),
                TextColumn::make('sale_total')
                    ->label('Total venta')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->icon(Heroicon::DocumentText)
                    ->iconColor('gray')
                    ->summarize(
                        Sum::make()
                            ->money('USD')
                            ->label('Σ ventas'),
                    ),
                TextColumn::make('cachea_paid_amount')
                    ->label('Pagado Cachea')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->color('success')
                    ->weight('semibold')
                    ->icon(Heroicon::CheckCircle)
                    ->iconColor('success')
                    ->summarize(
                        Sum::make()
                            ->money('USD')
                            ->label('Σ pagado'),
                    ),
                TextColumn::make('remainder')
                    ->label('Resto pendiente')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn (ConciliationCachea $record): string => CacheaPosPaymentSupport::remainderStatusColor((float) $record->remainder))
                    ->description(fn (ConciliationCachea $record): string => CacheaPosPaymentSupport::remainderStatusLabel((float) $record->remainder))
                    ->icon(fn (ConciliationCachea $record): Heroicon => (float) $record->remainder > 0.00001
                        ? Heroicon::Clock
                        : Heroicon::CheckCircle)
                    ->iconColor(fn (ConciliationCachea $record): string => CacheaPosPaymentSupport::remainderStatusColor((float) $record->remainder))
                    ->summarize(
                        Sum::make()
                            ->money('USD')
                            ->label('Σ resto'),
                    ),
                TextColumn::make('collection_status')
                    ->label('Estatus cobro')
                    ->badge()
                    ->formatStateUsing(fn (?ConciliationCacheaCollectionStatus $state): string => $state?->label() ?? ConciliationCacheaCollectionStatus::PendingCollection->label())
                    ->color(fn (?ConciliationCacheaCollectionStatus $state): string => $state?->badgeColor() ?? 'warning')
                    ->sortable()
                    ->icon(fn (?ConciliationCacheaCollectionStatus $state): Heroicon => $state === ConciliationCacheaCollectionStatus::AmountReceived
                        ? Heroicon::CheckBadge
                        : Heroicon::Clock)
                    ->iconColor(fn (?ConciliationCacheaCollectionStatus $state): string => $state?->badgeColor() ?? 'warning'),
                TextColumn::make('complement_payment_method')
                    ->label('Pago del resto')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, ConciliationCachea $record): string => (float) $record->remainder > 0.00001
                        ? CacheaPosPaymentSupport::complementLabel($state)
                        : 'No aplica')
                    ->color(fn (?string $state, ConciliationCachea $record): string => (float) $record->remainder > 0.00001
                        ? CacheaPosPaymentSupport::complementBadgeColor($state)
                        : 'gray')
                    ->icon(fn (ConciliationCachea $record): ?Heroicon => (float) $record->remainder > 0.00001
                        ? Heroicon::CreditCard
                        : null)
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Cajero')
                    ->placeholder('—')
                    ->searchable()
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->poll('15s')
            ->emptyStateHeading('Sin conciliaciones Cachea por cobrar')
            ->emptyStateDescription('Las ventas con Cachea aparecen aquí con estatus «Monto por cobrar». Cuando Cachea le pague a la farmacia, selecciónelas y use la acción masiva «Marcar monto recibido».')
            ->emptyStateIcon(Heroicon::QueueList)
            ->recordUrl(fn (ConciliationCachea $record): string => ConciliationCacheaResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                Filter::make('recorded_date_range')
                    ->label('Fecha de registro')
                    ->schema([
                        DatePicker::make('recorded_from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('recorded_until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (filled($data['recorded_from'] ?? null)) {
                            $query->where('recorded_at', '>=', Carbon::parse((string) $data['recorded_from'])->startOfDay());
                        }

                        if (filled($data['recorded_until'] ?? null)) {
                            $query->where('recorded_at', '<=', Carbon::parse((string) $data['recorded_until'])->endOfDay());
                        }
                    }),
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
                    ->searchable()
                    ->preload()
                    ->multiple(),
                TernaryFilter::make('has_pending_remainder')
                    ->label('Resto pendiente')
                    ->placeholder('Todos')
                    ->trueLabel('Con saldo pendiente')
                    ->falseLabel('Liquidadas')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('remainder', '>', 0),
                        false: fn (Builder $query): Builder => $query->where('remainder', '<=', 0),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('collection_status_visibility')
                    ->label('Visibilidad')
                    ->options(ConciliationCacheaCollectionStatus::filterOptions())
                    ->default('pending')
                    ->selectablePlaceholder(false)
                    ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->isAdministrator())
                    ->query(function (Builder $query, array $data): void {
                        ConciliationCacheaCollectionStatus::applyTableFilterScope(
                            $query,
                            self::normalizeSelectFilterValue($data['value'] ?? null) ?? 'pending',
                        );
                    }),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle')
                    ->icon(Heroicon::Eye),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAmountReceived')
                        ->label('Marcar monto recibido')
                        ->icon(Heroicon::CheckBadge)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar cobro de Cachea')
                        ->modalDescription('Los registros seleccionados pasarán a estatus «Monto recibido» y dejarán de mostrarse en este listado. Los datos se conservan en el sistema.')
                        ->modalSubmitActionLabel('Confirmar')
                        ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->isAdministrator())
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $updated = CacheaConciliationCollectionStatusService::markAsAmountReceived($records);

                            if ($updated === 0) {
                                Notification::make()
                                    ->title('Nada que actualizar')
                                    ->body('Seleccione registros con estatus «Monto por cobrar».')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Montos marcados como recibidos')
                                ->body("Se actualizaron {$updated} registro(s). Ya no aparecerán en la vista por cobrar.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    private static function normalizeSelectFilterValue(mixed $state): ?string
    {
        if (is_array($state) && array_key_exists('value', $state)) {
            $state = $state['value'];
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        return $state;
    }

    private static function formatSaleCell(ConciliationCachea $record): string
    {
        $saleUrl = $record->sale_id
            ? e(SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false))
            : null;

        $number = e($record->sale_number);

        $saleLink = $saleUrl !== null
            ? '<a href="'.$saleUrl.'" class="farmadoc-cachea-conciliation-sale__link">'.$number.'</a>'
            : '<span class="farmadoc-cachea-conciliation-sale__link">'.$number.'</span>';

        return '<div class="farmadoc-cachea-conciliation-sale">'
            .PosPaymentMethodOptions::cacheaTableBadgeHtml()
            .$saleLink
            .'</div>';
    }

    private static function branchBadgeColor(?int $branchId): string
    {
        if ($branchId === null || $branchId <= 0) {
            return 'gray';
        }

        $palette = ['primary', 'info', 'success', 'warning', 'danger'];
        $index = $branchId % count($palette);

        return $palette[$index];
    }
}
