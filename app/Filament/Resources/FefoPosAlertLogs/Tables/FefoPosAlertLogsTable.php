<?php

namespace App\Filament\Resources\FefoPosAlertLogs\Tables;

use App\Filament\Resources\FefoPosAlertLogs\FefoPosAlertLogResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Branch;
use App\Models\FefoPosAlertLog;
use App\Models\User;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FefoPosAlertLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('notified_at')
                    ->label('Alerta emitida')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::BellAlert)
                    ->iconColor('gray')
                    ->description(fn (FefoPosAlertLog $record): string => 'Cajero: '.($record->user?->name ?? '—')),
                TextColumn::make('severity')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                    ->color(fn ($state): string => $state?->badgeColor() ?? 'gray')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('info')
                    ->icon(Heroicon::BuildingStorefront)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product_name')
                    ->label('Producto')
                    ->description(fn (FefoPosAlertLog $record): string => 'Código: '.$record->product_code)
                    ->searchable(['product_name', 'product_code'])
                    ->wrap()
                    ->lineClamp(2)
                    ->icon(Heroicon::Cube)
                    ->url(fn (FefoPosAlertLog $record): ?string => $record->product_id
                        ? ProductResource::getUrl('view', ['record' => $record->product_id], isAbsolute: false)
                        : null)
                    ->color('primary'),
                TextColumn::make('expiration_month_year')
                    ->label('Lote FEFO')
                    ->description(fn (FefoPosAlertLog $record): string => $record->days_until_expiry.' días · '
                        .InventoryQuantityFormat::display($record->quantity_in_lot).' u.')
                    ->badge()
                    ->color(fn (FefoPosAlertLog $record): string => $record->severity?->badgeColor() ?? 'gray')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cajero')
                    ->description(fn (FefoPosAlertLog $record): string => (string) ($record->user?->email ?? '—'))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::UserCircle),
                TextColumn::make('sale_number')
                    ->label('Venta')
                    ->placeholder('Sin venta aún')
                    ->badge()
                    ->color(fn (FefoPosAlertLog $record): string => $record->isLinkedToSale() ? 'success' : 'gray')
                    ->icon(fn (FefoPosAlertLog $record): Heroicon => $record->isLinkedToSale()
                        ? Heroicon::CheckCircle
                        : Heroicon::Clock)
                    ->url(fn (FefoPosAlertLog $record): ?string => $record->sale_id
                        ? SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false)
                        : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sold_at')
                    ->label('Venta registrada')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('quantity_sold')
                    ->label('Cant. vendida')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => filled($state)
                        ? InventoryQuantityFormat::display($state)
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('response_minutes')
                    ->label('Tiempo respuesta')
                    ->state(fn (FefoPosAlertLog $record): ?string => self::formatResponseMinutes($record))
                    ->badge()
                    ->color(fn (FefoPosAlertLog $record): string => self::responseBadgeColor($record))
                    ->tooltip('Minutos entre la alerta FEFO y la venta vinculada'),
            ])
            ->defaultSort('notified_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin alertas FEFO registradas')
            ->emptyStateDescription('Cuando la caja muestre una alerta de lote por vencer, el evento aparecerá aquí en tiempo casi real.')
            ->emptyStateIcon(Heroicon::BellAlert)
            ->recordUrl(fn (FefoPosAlertLog $record): string => FefoPosAlertLogResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                TernaryFilter::make('linked_to_sale')
                    ->label('Vinculada a venta')
                    ->placeholder('Todas')
                    ->trueLabel('Con venta')
                    ->falseLabel('Sin venta')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('sale_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('sale_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('severity')
                    ->label('Tipo de alerta')
                    ->options([
                        'critical' => 'Urgente',
                        'warning' => 'Advertencia',
                    ]),
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
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name.' · '.$record->email)
                    ->searchable()
                    ->preload(),
                Filter::make('notified_between')
                    ->label('Fecha de alerta')
                    ->form([
                        DatePicker::make('notified_from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('notified_until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['notified_from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('notified_at', '>=', (string) $data['notified_from']),
                            )
                            ->when(
                                filled($data['notified_until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('notified_at', '<=', (string) $data['notified_until']),
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

    private static function formatResponseMinutes(FefoPosAlertLog $record): ?string
    {
        if (! $record->isLinkedToSale()) {
            return 'Pendiente';
        }

        $minutes = $record->minutesUntilSale();
        if ($minutes === null) {
            return '—';
        }

        if ($minutes <= 0) {
            return '< 1 min';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest > 0 ? "{$hours} h {$rest} min" : "{$hours} h";
    }

    private static function responseBadgeColor(FefoPosAlertLog $record): string
    {
        if (! $record->isLinkedToSale()) {
            return 'gray';
        }

        $minutes = $record->minutesUntilSale() ?? 999;

        return $minutes <= 15 ? 'success' : ($minutes <= 60 ? 'warning' : 'info');
    }
}
