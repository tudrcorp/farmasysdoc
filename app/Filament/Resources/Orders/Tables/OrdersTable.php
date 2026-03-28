<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['client', 'branch']))
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nº pedido')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->weight('medium')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->iconColor('gray'),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->description(fn (Order $record): ?string => self::formatClientDocument($record))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Order $record): string => (string) ($record->client?->name ?? '—'))
                    ->placeholder('—')
                    ->icon(Heroicon::User)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?OrderStatus $state): string => $state instanceof OrderStatus ? $state->label() : '—')
                    ->color(fn (?OrderStatus $state): string => self::statusBadgeColor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('convenio_type')
                    ->label('Convenio')
                    ->badge()
                    ->formatStateUsing(fn (?ConvenioType $state): string => $state instanceof ConvenioType ? $state->label() : '—')
                    ->color(fn (?ConvenioType $state): string => match ($state) {
                        ConvenioType::Particular => 'gray',
                        ConvenioType::Eps => 'success',
                        ConvenioType::PrivateInsurance => 'info',
                        ConvenioType::PrepaidMedicine => 'info',
                        ConvenioType::Corporate => 'warning',
                        ConvenioType::Other => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
                TextColumn::make('scheduled_delivery_at')
                    ->label('Entrega programada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('delivery_summary')
                    ->label('Destino / contacto')
                    ->state(fn (Order $record): string => self::formatDeliverySummary($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = "%{$search}%";
                        $query->where(function (Builder $q) use ($like): void {
                            $q->where('delivery_recipient_name', 'like', $like)
                                ->orWhere('delivery_phone', 'like', $like)
                                ->orWhere('delivery_address', 'like', $like)
                                ->orWhere('delivery_city', 'like', $like)
                                ->orWhere('delivery_state', 'like', $like);
                        });
                    })
                    ->limit(42)
                    ->tooltip(fn (Order $record): string => self::formatDeliverySummary($record, long: true))
                    ->placeholder('—')
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('convenio_partner_name')
                    ->label('Aseguradora / EPS')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('convenio_reference')
                    ->label('Ref. convenio')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_recipient_name')
                    ->label('Destinatario')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_phone')
                    ->label('Tel. entrega')
                    ->searchable()
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_address')
                    ->label('Dirección entrega')
                    ->limit(40)
                    ->tooltip(fn (Order $record): ?string => filled($record->delivery_address) ? $record->delivery_address : null)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_city')
                    ->label('Ciudad entrega')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_state')
                    ->label('Depto. / estado entrega')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dispatched_at')
                    ->label('Despachado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->label('Entregado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivery_assignee')
                    ->label('Asignado a')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax_total')
                    ->label('Impuestos')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_total')
                    ->label('Descuentos')
                    ->money()
                    ->sortable()
                    ->alignEnd()
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
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('No hay pedidos registrados')
            ->emptyStateDescription('Los pedidos aparecerán aquí cuando los crees desde el panel o los integres por API. Usa «Crear» para iniciar uno manualmente.')
            ->emptyStateIcon(Heroicon::ShoppingCart)
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(OrderStatus::options())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('convenio_type')
                    ->label('Tipo de convenio')
                    ->options(ConvenioType::options())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship(
                        name: 'client',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver pedido')
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

    private static function formatClientDocument(Order $order): ?string
    {
        $client = $order->client;
        if (! $client) {
            return null;
        }

        $doc = trim((string) ($client->document_number ?? ''));
        if ($doc === '') {
            return null;
        }

        $type = trim((string) ($client->document_type ?? ''));

        return $type !== '' ? "{$type} {$doc}" : $doc;
    }

    /**
     * @param  bool  $long  Si es true, añade la dirección completa (p. ej. tooltip).
     */
    private static function formatDeliverySummary(Order $order, bool $long = false): string
    {
        $location = trim(implode(', ', array_filter([$order->delivery_city, $order->delivery_state])));
        $head = array_filter([
            $order->delivery_recipient_name,
            $order->delivery_phone,
            $location !== '' ? $location : null,
        ]);
        $line = $head !== [] ? implode(' · ', $head) : '';

        if ($long && filled($order->delivery_address)) {
            return trim($line !== '' ? $line.' — '.$order->delivery_address : (string) $order->delivery_address);
        }

        if ($line !== '') {
            return $line;
        }

        if (filled($order->delivery_address)) {
            $address = (string) $order->delivery_address;

            return mb_strlen($address) > 48 ? mb_substr($address, 0, 48).'…' : $address;
        }

        return '—';
    }

    private static function statusBadgeColor(?OrderStatus $state): string
    {
        return match ($state) {
            OrderStatus::Pending => 'gray',
            OrderStatus::Confirmed => 'info',
            OrderStatus::Preparing => 'warning',
            OrderStatus::ReadyForDispatch => 'primary',
            OrderStatus::Dispatched => 'info',
            OrderStatus::InTransit => 'warning',
            OrderStatus::Delivered => 'success',
            OrderStatus::Cancelled => 'danger',
            default => 'gray',
        };
    }
}
