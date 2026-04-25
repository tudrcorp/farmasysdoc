<?php

namespace App\Filament\Resources\PurchaseHistories\Tables;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Models\PurchaseHistory;
use App\Support\Filament\BranchAuthScope;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['purchase', 'branch', 'accountsPayable']))
            ->emptyStateHeading('Aún no hay movimientos en el histórico')
            ->emptyStateDescription('Al guardar una compra como «Pagado de contado» se creará una fila aquí. Los abonos a cuentas por pagar se registran desde el detalle de CxP (acción «Registrar pago»).')
            ->emptyStateIcon(Heroicon::Clock)
            ->persistFiltersInSession()
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->columns([
                TextColumn::make('entry_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => PurchaseHistoryEntryType::label($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        PurchaseHistoryEntryType::COMPRA_CONTADO => 'success',
                        PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registrado en sistema')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('purchase.purchase_number')
                    ->label('Nº orden compra')
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::ShoppingCart)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->placeholder('—')
                    ->url(fn (PurchaseHistory $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null),
                TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->wrap()
                    ->icon(Heroicon::Truck),
                TextColumn::make('supplier_tax_id')
                    ->label('RIF')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('supplier_invoice_number')
                    ->label('Nº factura')
                    ->searchable(),
                TextColumn::make('issued_at')
                    ->label('Emisión factura')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('registered_in_system_date')
                    ->label('Registro documento')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchase_total_usd')
                    ->label('Total compra (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                TextColumn::make('purchase_total_ves_at_issue')
                    ->label('Total Bs (tasa emisión)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('total_ves_at_system_registration')
                    ->label('Total Bs (tasa día registro)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('payment_method')
                    ->label('Método pago')
                    ->formatStateUsing(fn (?string $state): string => PurchaseHistoryPaymentMethod::label($state))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('payment_form')
                    ->label('Forma pago')
                    ->formatStateUsing(fn (?string $state): string => PurchaseHistoryPaymentForm::label($state))
                    ->placeholder('—'),
                TextColumn::make('paid_at')
                    ->label('Fecha/hora pago')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('amount_paid_ves')
                    ->label('Monto pagado (Bs)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null ? self::formatBs((float) $state) : '—'),
                TextColumn::make('amount_paid_usd')
                    ->label('Equivalente pago (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2, ',', '.').' USD' : '—'),
                TextColumn::make('bcv_rate_at_payment')
                    ->label('Tasa BCV pago')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 6, ',', '.').' Bs/USD' : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('accounts_payable_id')
                    ->label('CxP')
                    ->formatStateUsing(fn (?int $state): string => $state ? 'CxP #'.$state : '—')
                    ->url(fn (PurchaseHistory $record): ?string => $record->accounts_payable_id
                        ? AccountsPayableResource::getUrl('view', ['record' => $record->accounts_payable_id], isAbsolute: false)
                        : null),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->label('Tipo')
                    ->options(PurchaseHistoryEntryType::options()),
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
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver detalle'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
