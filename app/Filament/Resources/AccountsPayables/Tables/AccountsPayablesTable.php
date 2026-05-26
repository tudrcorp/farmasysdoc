<?php

namespace App\Filament\Resources\AccountsPayables\Tables;

use App\Filament\Resources\AccountsPayables\Support\AccountsPayableBulkPaymentFormSchema;
use App\Filament\Resources\AccountsPayables\Support\AccountsPayablePaymentFormSchema;
use App\Filament\Resources\Branches\BranchResource;
use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsPayablePaymentRegistrar;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableBulkPaymentPayload;
use App\Support\Finance\AccountsPayableStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccountsPayablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => BranchAuthScope::apply($query)
                ->with(['purchase', 'branch']))
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
                    ->iconColor('gray'),
                TextColumn::make('supplier_tax_id')
                    ->label('RIF')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('supplier_invoice_number')
                    ->label('Nº factura')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('purchase_total_usd')
                    ->label('Total (USD)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' USD'),
                TextColumn::make('purchase_total_ves_at_issue')
                    ->label('Total factura (Bs, tasa emisión)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('original_balance_ves')
                    ->label('Saldo original (Bs)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state)),
                TextColumn::make('current_balance_ves')
                    ->label('Saldo al día (Bs)')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => self::formatBs((float) $state))
                    ->weight('medium'),
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
            ])
            ->recordActions([
                ViewAction::make(),
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
}
