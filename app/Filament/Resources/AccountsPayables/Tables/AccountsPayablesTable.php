<?php

namespace App\Filament\Resources\AccountsPayables\Tables;

use App\Filament\Resources\AccountsPayables\Support\AccountsPayableBulkPaymentFormSchema;
use App\Filament\Resources\AccountsPayables\Support\AccountsPayablePaymentFormSchema;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Tables\Columns\Summarizers\CopyableSum;
use App\Filament\Tables\Columns\Summarizers\CopyableSummarizer;
use App\Models\AccountsPayable;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsPayablePaymentRegistrar;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableBulkPaymentPayload;
use App\Support\Finance\AccountsPayableInvoiceTaxSnapshot;
use App\Support\Finance\AccountsPayableStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccountsPayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['purchase.purchaseBook', 'purchase.supplier.bankAccounts', 'branch']))
            ->columns([
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (?string $state): string => AccountsPayableStatus::label($state))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AccountsPayableStatus::PAGADO => 'success',
                        default => 'warning',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('purchase.purchase_number')
                    ->label('Nº orden compra')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::ShoppingCart)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingStorefront)
                    ->url(fn (AccountsPayable $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null),
                TextColumn::make('supplier_name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->icon(Heroicon::Truck)
                    ->iconColor('gray')
                    ->copyable()
                    ->copyMessage('Proveedor copiado')
                    ->tooltip('Clic para copiar el nombre del proveedor')
                    ->extraAttributes([
                        'class' => 'farmadoc-cxp-copyable',
                    ]),
                TextColumn::make('supplier_tax_id')
                    ->label('RIF')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('RIF copiado')
                    ->tooltip('Clic para copiar el RIF')
                    ->extraAttributes([
                        'class' => 'farmadoc-cxp-copyable',
                    ]),
                TextColumn::make('supplier_invoice_number')
                    ->label('Nº factura')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->weight('medium')
                    ->description(function (AccountsPayable $record): ?string {
                        $snapshot = AccountsPayableInvoiceTaxSnapshot::for($record);

                        if (filled($snapshot->purchaseNumber)) {
                            return 'Compra '.$snapshot->purchaseNumber;
                        }

                        return filled($record->supplier_invoice_number)
                            ? 'Sin compra vinculada'
                            : null;
                    })
                    ->tooltip('Ver detalle de la compra y sus totales')
                    ->action(self::viewPurchaseFromInvoiceAction()),
                TextColumn::make('supplier_control_number')
                    ->label('Nº control')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('issued_at')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('paid_at')
                    ->label('Fecha de pago')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchase_bcv_rate')
                    ->label('Tasa BCV registro')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (?float $state): string => ($state ?? 0) > 0 ? 'warning' : 'gray')
                    ->weight('bold')
                    ->icon(Heroicon::CurrencyDollar)
                    ->iconColor(fn (?float $state): string => ($state ?? 0) > 0 ? 'warning' : 'gray')
                    ->state(fn (AccountsPayable $record): ?float => AccountsPayableInvoiceTaxSnapshot::purchaseRegistrationBcvRate($record))
                    ->formatStateUsing(fn (?float $state): string => $state !== null
                        ? number_format($state, 4, ',', '.').' Bs/USD'
                        : '—')
                    ->tooltip('Tasa BCV del día del registro de la compra (usada para convertir el total a pagar a USD).'),
                TextColumn::make('purchase_total_usd')
                    ->label('Total (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD')
                    ->summarize(
                        CopyableSum::make()
                            ->label('')
                            ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.').' USD'),
                    ),
                TextColumn::make('purchase_total_ves_at_issue')
                    ->label('Total factura (Bs, tasa emisión)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                    ->summarize(
                        CopyableSum::make()
                            ->label('')
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) ($state ?? 0))),
                    ),
                TextColumn::make('invoice_tax_caused_ves')
                    ->label('IVA factura')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (?float $state): string => (float) ($state ?? 0) > 0 ? 'info' : 'gray')
                    ->weight('bold')
                    ->state(fn (AccountsPayable $record): ?float => AccountsPayableInvoiceTaxSnapshot::for($record)->taxCausedVes)
                    ->formatStateUsing(fn (?float $state): string => $state !== null
                        ? self::formatBs($state)
                        : '—')
                    ->tooltip('IVA tomado de Retenciones o, si no hay comprobante, de la compra (tax_total) según el Nº de factura.')
                    ->summarize(
                        CopyableSummarizer::make()
                            ->label('')
                            ->using(fn (CopyableSummarizer $summarizer): float => AccountsPayableInvoiceTaxSnapshot::sumTaxCausedForQuery(
                                $summarizer->getQuery() ?? AccountsPayable::query()->whereKey([]),
                            ))
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) ($state ?? 0))),
                    ),
                TextColumn::make('seniat_retention_percent')
                    ->label('% retención')
                    ->alignCenter()
                    ->badge()
                    ->state(fn (AccountsPayable $record): ?float => AccountsPayableInvoiceTaxSnapshot::for($record)->retentionPercent)
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state <= 0 => 'success',
                        $state >= 100 => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (?float $state): string => $state !== null
                        ? number_format($state, 0, ',', '.').'%'
                        : '—')
                    ->tooltip('Porcentaje de retención SENIAT configurado en la ficha del proveedor'),
                TextColumn::make('invoice_tax_retained_ves')
                    ->label('Total retenido')
                    ->alignEnd()
                    ->badge()
                    ->state(fn (AccountsPayable $record): ?float => AccountsPayableInvoiceTaxSnapshot::for($record)->taxRetainedVes)
                    ->color(fn (?float $state): string => (float) ($state ?? 0) > 0 ? 'warning' : 'gray')
                    ->weight('bold')
                    ->icon(fn (?float $state): Heroicon => (float) ($state ?? 0) > 0
                        ? Heroicon::ReceiptPercent
                        : Heroicon::MinusCircle)
                    ->iconColor(fn (?float $state): string => (float) ($state ?? 0) > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(function (?float $state, AccountsPayable $record): string {
                        if ($state === null) {
                            return AccountsPayableInvoiceTaxSnapshot::for($record)->purchaseId === null
                                ? '—'
                                : 'Sin retención';
                        }

                        return self::formatBs($state);
                    })
                    ->description(function (AccountsPayable $record): ?string {
                        $snapshot = AccountsPayableInvoiceTaxSnapshot::for($record);

                        if ($snapshot->retentionPercent === null) {
                            return null;
                        }

                        return 'IVA × '.number_format($snapshot->retentionPercent, 0, ',', '.').'%';
                    })
                    ->tooltip('Impuesto retenido = IVA de la factura × % SENIAT del proveedor (compra vinculada por Nº de factura).')
                    ->extraAttributes([
                        'class' => 'farmadoc-cxp-iva-retention',
                    ])
                    ->summarize(
                        CopyableSummarizer::make()
                            ->label('')
                            ->using(fn (CopyableSummarizer $summarizer): float => AccountsPayableInvoiceTaxSnapshot::sumTaxRetainedForQuery(
                                $summarizer->getQuery() ?? AccountsPayable::query()->whereKey([]),
                            ))
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) ($state ?? 0))),
                    ),
                TextColumn::make('invoice_amount_payable_ves')
                    ->label('Total a pagar')
                    ->alignEnd()
                    ->weight('semibold')
                    ->color('success')
                    ->state(fn (AccountsPayable $record): float => AccountsPayableInvoiceTaxSnapshot::amountPayableVes($record))
                    ->formatStateUsing(fn (float $state): string => self::formatBs($state))
                    ->copyable()
                    ->copyMessage('Total a pagar copiado')
                    ->copyableState(fn (float $state): string => number_format($state, 2, ',', '.'))
                    ->description(function (AccountsPayable $record): ?string {
                        $retained = AccountsPayableInvoiceTaxSnapshot::for($record)->taxRetainedVes;

                        if ($retained === null || (float) $retained <= 0) {
                            return 'Sin descontar retención';
                        }

                        return 'Factura − '.self::formatBs((float) $retained);
                    })
                    ->tooltip('Clic para copiar. Total factura (Bs, tasa emisión) menos el valor retenido por SENIAT.')
                    ->extraAttributes([
                        'class' => 'farmadoc-cxp-copyable',
                    ])
                    ->summarize(
                        CopyableSummarizer::make()
                            ->label('')
                            ->using(fn (CopyableSummarizer $summarizer): float => AccountsPayableInvoiceTaxSnapshot::sumAmountPayableForQuery(
                                $summarizer->getQuery() ?? AccountsPayable::query()->whereKey([]),
                            ))
                            ->formatStateUsing(fn ($state): string => self::formatBs((float) ($state ?? 0))),
                    ),
                TextColumn::make('last_balance_recalculated_at')
                    ->label('Último recálculo')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (AccountsPayable $record): bool => $record->status === AccountsPayableStatus::POR_PAGAR,
            )
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(AccountsPayableStatus::options()),
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
                SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->options(
                        fn (): array => Supplier::query()
                            ->where('is_active', true)
                            ->orderBy('legal_name')
                            ->get()
                            ->mapWithKeys(fn (Supplier $supplier): array => [
                                (string) $supplier->id => $supplier->displayName(),
                            ])
                            ->all(),
                    )
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $supplierId = $data['value'] ?? null;

                        if (blank($supplierId)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'purchase',
                            fn (Builder $purchaseQuery): Builder => $purchaseQuery->where('supplier_id', $supplierId),
                        );
                    }),
                Filter::make('issued_at_range')
                    ->label('Fecha de factura')
                    ->schema([
                        DatePicker::make('issued_from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('issued_until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['issued_from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('issued_at', '>=', (string) $data['issued_from']),
                            )
                            ->when(
                                filled($data['issued_until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('issued_at', '<=', (string) $data['issued_until']),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('viewSupplierBankDetails')
                    ->label('Datos bancarios')
                    ->icon(Heroicon::BuildingLibrary)
                    ->color('info')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (AccountsPayable $record): string => 'Datos bancarios · '.($record->supplier_name ?: 'Proveedor'))
                    ->modalDescription('RIF, total a pagar y cuentas del proveedor. Clic en cualquier dato para copiarlo.')
                    ->modalIcon(Heroicon::BuildingLibrary)
                    ->modalIconColor('info')
                    ->modalContent(function (AccountsPayable $record): View {
                        $supplier = self::resolveSupplierForAccountsPayable($record);
                        $supplier?->loadMissing('bankAccounts');

                        $taxId = filled($record->supplier_tax_id)
                            ? (string) $record->supplier_tax_id
                            : ($supplier?->tax_id);

                        return view('filament.accounts-payables.supplier-bank-details-modal', [
                            'accountsPayable' => $record,
                            'supplier' => $supplier,
                            'bankAccounts' => $supplier?->bankAccounts ?? collect(),
                            'amountPayableVes' => AccountsPayableInvoiceTaxSnapshot::amountPayableVes($record),
                            'supplierName' => filled($record->supplier_name)
                                ? (string) $record->supplier_name
                                : ($supplier?->displayName() ?? '—'),
                            'supplierTaxId' => filled($taxId) ? (string) $taxId : null,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn (Action $action): Action => $action
                        ->label('Cerrar')
                        ->color('gray')),
                Action::make('registerPayment')
                    ->label('Registrar pago')
                    ->icon(Heroicon::Banknotes)
                    ->color('success')
                    ->modalWidth(Width::Large)
                    ->modalHeading('Registrar pago al proveedor')
                    ->modalDescription(function (AccountsPayable $record): string {
                        $usd = round((float) ($record->remaining_principal_usd ?? $record->purchase_total_usd), 2);

                        return 'Principal pendiente (USD): '.number_format($usd, 2, ',', '.')
                            .'. Los montos en Bs deben cuadrar con la tasa BCV del día actual.';
                    })
                    ->visible(fn (AccountsPayable $record): bool => $record->status === AccountsPayableStatus::POR_PAGAR)
                    ->fillForm(fn (AccountsPayable $record): array => AccountsPayablePaymentFormSchema::defaultStateForRecord($record))
                    ->schema(AccountsPayablePaymentFormSchema::paymentFields(true))
                    ->action(function (AccountsPayable $record, array $data): void {
                        AuditLogger::record(
                            event: 'filament_accounts_payable_single_payment_submit',
                            description: 'CxP: el usuario envió el formulario de pago desde el listado.',
                            auditableType: AccountsPayable::class,
                            auditableId: (string) $record->getKey(),
                            auditableLabel: $record->supplier_invoice_number,
                            properties: [
                                'payment_method' => $data['payment_method'] ?? null,
                                'payment_form' => $data['payment_form'] ?? null,
                            ],
                        );

                        try {
                            app(AccountsPayablePaymentRegistrar::class)->register($record, $data);
                            Notification::make()
                                ->title('Pago registrado')
                                ->body('Se actualizó la cuenta por pagar y quedó asentado en el histórico de compras.')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            $first = collect($e->errors())->flatten()->first();
                            AuditLogger::record(
                                event: 'filament_accounts_payable_single_payment_validation_failed',
                                description: 'CxP: validación rechazó el pago desde el listado.',
                                auditableType: AccountsPayable::class,
                                auditableId: (string) $record->getKey(),
                                properties: ['errors' => $e->errors()],
                            );
                            Notification::make()
                                ->title('No se pudo registrar el pago')
                                ->body(is_string($first) ? $first : 'Revise los datos e intente de nuevo.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('registerBulkPayment')
                        ->label('Pagar seleccionadas')
                        ->icon(Heroicon::Banknotes)
                        ->color('success')
                        ->modalWidth(Width::FiveExtraLarge)
                        ->modalHeading('Pago masivo a proveedores')
                        ->modalDescription('Revise el detalle de cada cuenta por pagar, los totales calculados con la tasa BCV del día y confirme los datos del pago. Solo aplica a cuentas en estado «Por pagar».')
                        ->modalSubmitActionLabel('Confirmar pago masivo')
                        ->deselectRecordsAfterCompletion()
                        ->before(function (Collection $records): void {
                            $payload = AccountsPayableBulkPaymentPayload::fromSelection($records);
                            if ($payload->ok) {
                                return;
                            }

                            Notification::make()
                                ->title('No se puede continuar')
                                ->body((string) $payload->error)
                                ->danger()
                                ->send();

                            throw new Halt;
                        })
                        ->fillForm(fn (Collection $records): array => AccountsPayableBulkPaymentFormSchema::fillFormStateFromPayload(
                            AccountsPayableBulkPaymentPayload::fromSelection($records),
                        ))
                        ->schema(AccountsPayableBulkPaymentFormSchema::modalSchema())
                        ->action(function (Collection $records, array $data): void {
                            if (filled($data['_bulk_error'] ?? null)) {
                                Notification::make()
                                    ->title('No se puede continuar')
                                    ->body((string) $data['_bulk_error'])
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $payload = AccountsPayableBulkPaymentPayload::fromSelection($records);
                            if (! $payload->ok) {
                                Notification::make()
                                    ->title('No se puede continuar')
                                    ->body((string) $payload->error)
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $shared = [
                                'payment_method' => $data['payment_method'] ?? null,
                                'payment_form' => $data['payment_form'] ?? null,
                                'paid_at' => $data['paid_at'] ?? null,
                                'payment_reference' => $data['payment_reference'] ?? null,
                                'notes' => $data['notes'] ?? null,
                            ];

                            AuditLogger::record(
                                event: 'filament_accounts_payable_bulk_payment_submit',
                                description: 'CxP: el usuario confirmó pago masivo desde el listado.',
                                properties: [
                                    'accounts_payable_ids' => $records->pluck('id')->all(),
                                    'payment_method' => $shared['payment_method'] ?? null,
                                    'payment_form' => $shared['payment_form'] ?? null,
                                ],
                            );

                            try {
                                app(AccountsPayablePaymentRegistrar::class)->registerBulkFullSettlement($records, $shared);
                                Notification::make()
                                    ->title('Pagos registrados')
                                    ->body('Se actualizaron '.count($payload->selectedLines).' cuenta(s) por pagar y el histórico de compras.')
                                    ->success()
                                    ->send();
                            } catch (ValidationException $e) {
                                $first = collect($e->errors())->flatten()->first();
                                AuditLogger::record(
                                    event: 'filament_accounts_payable_bulk_payment_failed',
                                    description: 'CxP: error de validación o negocio en pago masivo.',
                                    properties: [
                                        'accounts_payable_ids' => $records->pluck('id')->all(),
                                        'errors' => $e->errors(),
                                    ],
                                );
                                Notification::make()
                                    ->title('No se pudo registrar el pago masivo')
                                    ->body(is_string($first) ? $first : 'Revise los datos e intente de nuevo.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    private static function viewPurchaseFromInvoiceAction(): Action
    {
        return Action::make('viewPurchaseFromInvoice')
            ->modalHeading(function (AccountsPayable $record): string {
                $purchase = self::resolvePurchaseForAccountsPayable($record);
                $invoice = filled($record->supplier_invoice_number)
                    ? (string) $record->supplier_invoice_number
                    : '—';

                if ($purchase === null) {
                    return 'Factura '.$invoice;
                }

                return 'Factura '.$invoice.' · '.$purchase->purchase_number;
            })
            ->modalDescription('Resumen de totales de la factura y la retención asociada.')
            ->modalIcon(Heroicon::Banknotes)
            ->modalIconColor('primary')
            ->modalWidth(Width::Large)
            ->modalContent(function (AccountsPayable $record): View {
                $purchase = self::resolvePurchaseForAccountsPayable($record);
                $purchase?->loadMissing('supplier');

                return view('filament.accounts-payables.purchase-from-invoice-modal', [
                    'purchase' => $purchase,
                    'accountsPayable' => $record,
                    'taxSnapshot' => AccountsPayableInvoiceTaxSnapshot::for($record),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cerrar')
                ->color('gray'));
    }

    private static function resolvePurchaseForAccountsPayable(AccountsPayable $record): ?Purchase
    {
        $record->loadMissing('purchase');

        if ($record->purchase instanceof Purchase) {
            return $record->purchase;
        }

        $invoiceNumber = trim((string) ($record->supplier_invoice_number ?? ''));

        if ($invoiceNumber === '') {
            return null;
        }

        return Purchase::query()
            ->where('supplier_invoice_number', $invoiceNumber)
            ->latest('id')
            ->first();
    }

    private static function resolveSupplierForAccountsPayable(AccountsPayable $record): ?Supplier
    {
        $purchase = self::resolvePurchaseForAccountsPayable($record);
        $purchase?->loadMissing('supplier');

        if ($purchase?->supplier instanceof Supplier) {
            return $purchase->supplier;
        }

        $taxId = trim((string) ($record->supplier_tax_id ?? ''));
        if ($taxId !== '') {
            $byTaxId = Supplier::query()
                ->where('tax_id', $taxId)
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first();

            if ($byTaxId instanceof Supplier) {
                return $byTaxId;
            }
        }

        $name = trim((string) ($record->supplier_name ?? ''));
        if ($name === '') {
            return null;
        }

        return Supplier::query()
            ->where(function (Builder $query) use ($name): void {
                $query->where('legal_name', $name)
                    ->orWhere('trade_name', $name);
            })
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->first();
    }
}
