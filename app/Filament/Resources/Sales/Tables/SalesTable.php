<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleStatus;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleVoidService;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\SaleEffectiveDateScope;
use App\Support\Filament\SaleIosBreakdownHtml;
use App\Support\Filament\SlideoverModalScrollFix;
use App\Support\Sales\PosPaymentMethodOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::applyToSalesQuery($query)
                ->with(['branch', 'client', 'items', 'conciliationCachea'])
                ->withCount('items'))
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Nº venta')
                    ->badge()
                    ->color('primary')
                    ->tooltip('Clic para ver el desglose completo')
                    ->action(
                        Action::make('viewSaleIosBreakdown')
                            ->label('Desglose de la venta')
                            ->icon(Heroicon::RectangleStack)
                            ->modalIcon(Heroicon::RectangleStack)
                            ->slideOver()
                            ->extraModalWindowAttributes(SlideoverModalScrollFix::extraModalWindowAttributes())
                            ->modalWidth(Width::FourExtraLarge)
                            ->modalHeading(fn (Sale $record): string => 'Venta '.$record->sale_number)
                            ->modalDescription('Totales en USD, cobro, tasa BCV de referencia y cada producto. Cierra con «Listo» o la X.')
                            ->modalFooterActionsAlignment(Alignment::End)
                            ->modalSubmitActionLabel('Listo')
                            ->modalCancelAction(false)
                            ->schema([
                                TextEntry::make('sale_number')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                                    ->html()
                                    ->extraAttributes([
                                        'class' => 'farmadoc-sale-slideover-entry',
                                    ])
                                    ->formatStateUsing(fn (Sale $record): string => SaleIosBreakdownHtml::build($record)->toHtml()),
                            ])
                            ->action(static fn () => null),
                    )
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->weight('medium')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color(fn (Sale $record): string => self::branchBadgeColor($record->branch_id))
                    ->description(fn (Sale $record): ?string => filled($record->branch?->code)
                        ? 'Código: '.$record->branch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('branch', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->limit(28)
                    ->tooltip(fn (Sale $record): string => $record->branch?->name ?? 'Sin sucursal')
                    ->url(fn (Sale $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null)
                    ->openUrlInNewTab(false),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->placeholder('Mostrador / sin cliente')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('client', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('document_number', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $query->orderBy(
                            Client::query()->select('name')->whereColumn('clients.id', 'sales.client_id'),
                            $direction,
                        );
                    })
                    ->limit(32)
                    ->tooltip(fn (Sale $record): string => $record->client?->name ?? 'Venta sin cliente registrado')
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->url(fn (Sale $record): ?string => $record->client_id
                        ? ClientResource::getUrl('view', ['record' => $record->client_id], isAbsolute: false)
                        : null)
                    ->openUrlInNewTab(false),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?SaleStatus $state): string => $state instanceof SaleStatus ? $state->label() : '—')
                    ->color(fn (?SaleStatus $state): string => match ($state) {
                        SaleStatus::Draft => 'gray',
                        SaleStatus::Completed => 'success',
                        SaleStatus::Cancelled => 'danger',
                        SaleStatus::Refunded => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Líneas')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->icon(Heroicon::RectangleStack)
                    ->iconColor('gray')
                    ->tooltip('Cantidad de ítems en el detalle de la venta'),
                TextColumn::make('total')
                    ->label('Total')
                    ->money()
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('gray'),
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
                TextColumn::make('payment_method')
                    ->label('Medio de pago')
                    ->formatStateUsing(fn (Sale $record): string => self::formatPaymentMethodCellForRecord($record))
                    ->html()
                    ->searchable()
                    ->placeholder('—')
                    ->icon(fn (Sale $record): ?Heroicon => PosPaymentMethodOptions::isCachea(
                        PosPaymentMethodOptions::effectiveSalePaymentMethod($record)
                    )
                        ? null
                        : Heroicon::CreditCard)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('payment_usd')
                    ->label('Pago USD')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->icon(Heroicon::CurrencyDollar)
                    ->iconColor('gray'),
                TextColumn::make('payment_ves')
                    ->label('Pago Bs.')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? 'Bs. '.number_format((float) $state, 2, ',', '.')
                        : '—')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('gray'),
                TextColumn::make('bcv_ves_per_usd')
                    ->label('Tasa BCV')
                    ->tooltip('Bolívares por 1 USD aplicados al cobrar (referencia para validar el pago en Bs.)')
                    ->formatStateUsing(fn ($state): string => $state !== null && (float) $state > 0
                        ? 'Bs. '.number_format((float) $state, 6, ',', '.').' / USD'
                        : '—')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->icon(Heroicon::ChartBar)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('payment_status')
                    ->label('Estado cobro')
                    ->formatStateUsing(fn (?string $state): string => self::formatPaymentStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => self::paymentStatusColor($state))
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sold_at')
                    ->label('Fecha venta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
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
                    ->label('Registro creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sold_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->emptyStateHeading('Sin ventas en el período')
            ->emptyStateDescription('Por defecto solo se listan las ventas del día actual. Ajuste «Desde» y «Hasta» en los filtros para consultar otro rango.')
            ->emptyStateIcon(Heroicon::ShoppingBag)
            ->recordUrl(fn (Sale $record): string => SaleResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                Filter::make('sold_date_range')
                    ->label('Fecha de venta')
                    ->schema([
                        DatePicker::make('sold_from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('sold_until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->default(fn (): array => [
                        'sold_from' => now()->toDateString(),
                        'sold_until' => now()->toDateString(),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        SaleEffectiveDateScope::apply(
                            $query,
                            filled($data['sold_from'] ?? null) ? (string) $data['sold_from'] : null,
                            filled($data['sold_until'] ?? null) ? (string) $data['sold_until'] : null,
                        );
                    }),
                // SelectFilter::make('status')
                //     ->label('Estado')
                //     ->options(SaleStatus::options())
                //     ->multiple()
                //     ->searchable(),
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
                // TernaryFilter::make('client_assigned')
                //     ->label('Cliente')
                //     ->placeholder('Todos')
                //     ->trueLabel('Con cliente')
                //     ->falseLabel('Sin cliente')
                //     ->queries(
                //         true: fn (Builder $query) => $query->whereNotNull('client_id'),
                //         false: fn (Builder $query) => $query->whereNull('client_id'),
                //     ),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver venta')
                        ->icon(Heroicon::Eye),
                    Action::make('printFiscalReceipt')
                        ->label(fn (Sale $record): string => $record->status === SaleStatus::Cancelled
                            ? 'Nota de crédito'
                            : ($record->payment_method === 'credito_cliente'
                                ? 'Nota de entrega'
                                : 'Factura fiscal'))
                        ->icon(Heroicon::Printer)
                        ->color('gray')
                        ->tooltip(fn (Sale $record): string => $record->status === SaleStatus::Cancelled
                            ? 'Reimprimir nota de crédito de la anulación'
                            : ($record->payment_method === 'credito_cliente'
                                ? 'Documento de entrega para ventas a crédito'
                                : 'Ticket térmico (texto/ESC-POS) — prueba de impresión'))
                        ->url(fn (Sale $record): string => match (true) {
                            $record->status === SaleStatus::Cancelled => route('sales.credit-note.print', $record),
                            $record->payment_method === 'credito_cliente' => route('sales.delivery-note.print', $record),
                            default => route('sales.fiscal-receipt', $record),
                        })
                        ->openUrlInNewTab(),
                    Action::make('voidSale')
                        ->label('Anular venta')
                        ->icon(Heroicon::XCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Anular venta')
                        ->modalDescription(fn (Sale $record): string => 'Se revertirá el inventario de los productos de la venta '.$record->sale_number
                            .', la venta quedará como «Cancelada» y se abrirá la impresión de la nota de crédito fiscal.')
                        ->modalSubmitActionLabel('Sí, anular venta')
                        ->visible(fn (Sale $record): bool => $record->status === SaleStatus::Completed
                            && Auth::user() instanceof User
                            && Auth::user()->canVoidSales())
                        ->action(function (Sale $record, Action $action): void {
                            try {
                                $sale = app(SaleVoidService::class)->void($record, Auth::user());
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->title('No se pudo anular la venta')
                                    ->body(collect($e->errors())->flatten()->implode(' '))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $printUrl = route('sales.credit-note.print', $sale);

                            $action->getLivewire()->js(
                                'window.open('.Js::from($printUrl).', "_blank")'
                            );

                            Notification::make()
                                ->title('Venta anulada')
                                ->body('Inventario restaurado. Se abrió la nota de crédito para imprimir.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    private static function formatPaymentMethodCellForRecord(Sale $record): string
    {
        return self::formatPaymentMethodCell(PosPaymentMethodOptions::effectiveSalePaymentMethod($record));
    }

    private static function formatPaymentMethodCell(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        if (PosPaymentMethodOptions::isCachea($value)) {
            return PosPaymentMethodOptions::cacheaTableBadgeHtml();
        }

        return e(self::formatPaymentMethodLabel($value));
    }

    private static function formatPaymentMethodLabel(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'cachea' => 'Cachea',
            'pago_movil' => 'Pago Movil',
            'mixed' => 'Pago Multiple',
            'credito_cliente' => 'Crédito · cuenta por cobrar',
            'punto_venta_ves' => 'Punto de venta',
            'transfer_usd' => 'Transferencias USD',
            'traslado_sucursal' => 'Traslado entre sucursales (costo)',
            'cash', 'efectivo' => 'Efectivo',
            'card', 'tarjeta', 'debit', 'credit' => 'Tarjeta',
            'transfer', 'transferencia', 'nequi', 'daviplata' => 'Transferencia / digital',
            default => $value,
        };
    }

    private static function formatPaymentStatusLabel(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'paid', 'pagado', 'cobrado' => 'Pagado',
            'pending', 'pendiente' => 'Pendiente',
            'partial', 'parcial' => 'Parcial',
            'refunded', 'reembolsado' => 'Reembolsado',
            default => $value,
        };
    }

    private static function paymentStatusColor(?string $value): string
    {
        if (blank($value)) {
            return 'gray';
        }

        return match (strtolower(trim($value))) {
            'paid', 'pagado', 'cobrado' => 'success',
            'pending', 'pendiente' => 'warning',
            'partial', 'parcial' => 'info',
            'refunded', 'reembolsado' => 'danger',
            default => 'gray',
        };
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
