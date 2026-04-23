<?php

namespace App\Filament\Resources\AccountsPayables\Tables;

use App\Filament\Resources\AccountsPayables\Support\AccountsPayablePaymentFormSchema;
use App\Filament\Resources\Branches\BranchResource;
use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsPayablePaymentRegistrar;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableBulkPaymentPayload;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
                        ->label('Pago masivo')
                        ->icon(Heroicon::Banknotes)
                        ->color('success')
                        ->modalWidth(Width::FiveExtraLarge)
                        ->modalHeading('Registrar pago masivo')
                        ->modalDescription('Se liquidará el principal pendiente en USD de cada fila seleccionada, usando la tasa BCV del día actual. La misma forma y referencia de pago se replicará en cada movimiento de histórico.')
                        ->deselectRecordsAfterCompletion()
                        ->fillForm(function (Collection $records): array {
                            $payload = AccountsPayableBulkPaymentPayload::fromSelection($records);

                            if (! $payload->ok) {
                                return [
                                    '_bulk_error' => $payload->error,
                                    '_lines_payload_json' => '[]',
                                    '_total_usd' => 0,
                                    '_total_ves' => 0,
                                    '_bcv_rate' => $payload->rate,
                                    'payment_method' => PurchaseHistoryPaymentMethod::TRANSFERENCIA,
                                    'payment_form' => PurchaseHistoryPaymentForm::LIQUIDACION_TOTAL,
                                    'paid_at' => now(),
                                    'payment_reference' => '',
                                    'notes' => null,
                                ];
                            }

                            return [
                                '_bulk_error' => null,
                                '_lines_payload_json' => json_encode($payload->lines, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                '_total_usd' => $payload->totalUsd,
                                '_total_ves' => $payload->totalVes,
                                '_bcv_rate' => $payload->rate,
                                'payment_method' => PurchaseHistoryPaymentMethod::TRANSFERENCIA,
                                'payment_form' => PurchaseHistoryPaymentForm::LIQUIDACION_TOTAL,
                                'paid_at' => now(),
                                'payment_reference' => '',
                                'notes' => null,
                            ];
                        })
                        ->schema(array_merge([
                            Hidden::make('_bulk_error'),
                            Hidden::make('_lines_payload_json'),
                            Hidden::make('_total_usd'),
                            Hidden::make('_total_ves'),
                            Hidden::make('_bcv_rate'),
                            ViewField::make('_bulk_summary')
                                ->view('filament.accounts-payables.bulk-payment-summary')
                                ->columnSpanFull(),
                        ], AccountsPayablePaymentFormSchema::paymentFields(false)))
                        ->action(function (Collection $records, array $data): void {
                            $payload = AccountsPayableBulkPaymentPayload::fromSelection($records);
                            if (! $payload->ok) {
                                Notification::make()
                                    ->title('No se puede continuar')
                                    ->body((string) $payload->error)
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $shared = Arr::only($data, [
                                'payment_method',
                                'payment_form',
                                'paid_at',
                                'payment_reference',
                                'notes',
                            ]);

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
                                    ->body('Se actualizaron '.count($payload->lines).' cuenta(s) por pagar y el histórico de compras.')
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
