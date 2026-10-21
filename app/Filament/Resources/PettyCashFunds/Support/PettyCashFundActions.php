<?php

namespace App\Filament\Resources\PettyCashFunds\Support;

use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Finance\PettyCashExpenseRegistrar;
use App\Services\Finance\PettyCashFundApprover;
use App\Services\Finance\PettyCashFundReplenishmentRegistrar;
use App\Support\Finance\NotifyAdministratorsPettyCashRequested;
use App\Support\Finance\PettyCashMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PettyCashFundActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar y cargar comprobante')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->modalHeading(fn (PettyCashFund $record): string => $record->withReplenishmentQualifier('Aprobar caja chica'))
            ->modalDescription(fn (PettyCashFund $record): string => $record->isReplenishment()
                ? 'Transfiera solo '
                    .PettyCashMoney::formatUsd($record->transferAmountUsd())
                    .' / '
                    .PettyCashMoney::formatVes($record->transferAmountVes())
                    .'. El saldo anterior ya está en el sistema; el fondo quedará en '
                    .PettyCashMoney::formatUsd((float) $record->amount_usd)
                    .' / '
                    .PettyCashMoney::formatVes((float) $record->amount_ves)
                    .'. Adjunte comprobante y referencia.'
                : 'Adjunte la captura del pago y el número de referencia o comprobante.')
            ->modalIcon(Heroicon::CheckCircle)
            ->modalIconColor('success')
            ->modalWidth(Width::Medium)
            ->slideOver()
            ->visible(fn (PettyCashFund $record): bool => self::canApprove($record))
            ->schema([
                PettyCashUploads::paymentProof(),
                TextInput::make('payment_reference')
                    ->label('Referencia o número de comprobante')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
            ])
            ->modalSubmitActionLabel('Aprobar')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--success',
                ]))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancelar')
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]))
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--success',
            ])
            ->action(function (array $data, PettyCashFund $record): void {
                try {
                    $path = self::storedUploadPath($data['payment_proof_path'] ?? null);
                    if ($path === '') {
                        throw new \InvalidArgumentException('Adjunte el comprobante de pago.');
                    }

                    app(PettyCashFundApprover::class)->approve(
                        $record,
                        self::actor(),
                        $path,
                        (string) ($data['payment_reference'] ?? ''),
                    );
                    Notification::make()->title('Caja chica aprobada')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('No se pudo aprobar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Rechazar solicitud')
            ->modalDescription('La sucursal podrá volver a solicitar caja chica.')
            ->visible(fn (PettyCashFund $record): bool => self::canApprove($record))
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Motivo (opcional)')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--danger',
            ])
            ->action(function (array $data, PettyCashFund $record): void {
                try {
                    app(PettyCashFundApprover::class)->reject(
                        $record,
                        self::actor(),
                        isset($data['rejection_reason']) ? (string) $data['rejection_reason'] : null,
                    );
                    Notification::make()->title('Solicitud rechazada')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('No se pudo rechazar')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function registerExpense(): Action
    {
        return Action::make('registerExpense')
            ->label('Registrar gasto')
            ->icon(Heroicon::ReceiptPercent)
            ->color('primary')
            ->modalHeading('Registrar gasto de caja chica')
            ->modalDescription(fn (PettyCashFund $record): string => 'Disponible: '
                .PettyCashMoney::formatUsd((float) $record->remaining_usd)
                .' / '
                .PettyCashMoney::formatVes((float) $record->remaining_ves)
                .'. El monto se descuenta del saldo hasta agotar la caja.')
            ->modalIcon(Heroicon::ReceiptPercent)
            ->modalWidth(Width::Medium)
            ->slideOver()
            ->extraModalWindowAttributes([
                'class' => 'farmadoc-petty-cash-expense-modal fi-fixed-positioning-context',
            ])
            ->visible(fn (PettyCashFund $record): bool => self::canRegisterExpense($record))
            ->schema([
                DateTimePicker::make('spent_at')
                    ->label('Fecha del gasto')
                    ->seconds(false)
                    ->default(now())
                    ->required(),
                Select::make('currency')
                    ->label('Moneda del gasto')
                    ->options(PettyCashCurrency::options())
                    ->default(PettyCashCurrency::Usd->value)
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required()
                    ->prefix(fn (Get $get): string => PettyCashCurrency::tryFrom((string) $get('currency'))?->prefix() ?? 'US$'),
                Textarea::make('description')
                    ->label('Qué se gastó')
                    ->rows(3)
                    ->required()
                    ->maxLength(2000)
                    ->columnSpanFull(),
                PettyCashUploads::expenseReceipt(),
            ])
            ->modalSubmitActionLabel('Registrar')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancelar')
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]))
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ])
            ->action(function (array $data, PettyCashFund $record): void {
                try {
                    $path = self::storedUploadPath($data['receipt_path'] ?? null);
                    if ($path === '') {
                        throw new \InvalidArgumentException('Adjunte la foto de la factura o del ticket.');
                    }

                    app(PettyCashExpenseRegistrar::class)->register($record, self::actor(), [
                        'spent_at' => $data['spent_at'] ?? now(),
                        'description' => (string) ($data['description'] ?? ''),
                        'currency' => $data['currency'] ?? PettyCashCurrency::Usd->value,
                        'amount' => $data['amount'] ?? 0,
                        'receipt_path' => $path,
                    ]);
                    Notification::make()->title('Gasto registrado')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('No se pudo registrar el gasto')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function replenish(): Action
    {
        return Action::make('replenish')
            ->label('Reposición')
            ->icon(Heroicon::ArrowPath)
            ->color('warning')
            ->modalHeading('Reposición de caja chica')
            ->modalDescription(fn (PettyCashFund $record): string => 'Indique cuánto debe transferirle el banco (monto nuevo). El saldo '
                .PettyCashMoney::formatUsd((float) $record->remaining_usd)
                .' / '
                .PettyCashMoney::formatVes((float) $record->remaining_ves)
                .' no se vuelve a pagar: solo se suma en el sistema al total del fondo. La caja actual quedará cerrada hasta que administración apruebe.')
            ->modalIcon(Heroicon::ArrowPath)
            ->modalWidth(Width::Medium)
            ->slideOver()
            ->extraModalWindowAttributes([
                'class' => 'farmadoc-petty-cash-expense-modal fi-fixed-positioning-context',
            ])
            ->visible(fn (PettyCashFund $record): bool => self::canReplenish($record))
            ->schema([
                DatePicker::make('requested_at')
                    ->label('Fecha de solicitud')
                    ->default(now())
                    ->required()
                    ->native(false),
                Select::make('requested_currency')
                    ->label('Moneda del monto nuevo')
                    ->options(PettyCashCurrency::options())
                    ->default(PettyCashCurrency::Usd->value)
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('requested_amount')
                    ->label('Monto a transferir (pago nuevo)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required()
                    ->prefix(fn (Get $get): string => PettyCashCurrency::tryFrom((string) $get('requested_currency'))?->prefix() ?? 'US$')
                    ->helperText(fn (PettyCashFund $record): string => 'Ese monto es el que debe enviar el banco. En el sistema verá además '
                        .PettyCashMoney::formatUsd((float) $record->remaining_usd)
                        .' / '
                        .PettyCashMoney::formatVes((float) $record->remaining_ves)
                        .' del saldo anterior sumados al total.'),
                Textarea::make('notes')
                    ->label('Nota o descripción')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ])
            ->modalSubmitActionLabel('Solicitar reposición')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancelar')
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]))
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--warning',
            ])
            ->action(function (array $data, PettyCashFund $record): void {
                try {
                    $replacement = app(PettyCashFundReplenishmentRegistrar::class)->register(
                        $record,
                        self::actor(),
                        $data,
                    );

                    try {
                        app(NotifyAdministratorsPettyCashRequested::class)->notify(
                            $replacement,
                            PettyCashFundResource::currentUser(),
                        );
                    } catch (Throwable $exception) {
                        Log::warning('No se pudo enviar WhatsApp de reposición de caja chica', [
                            'petty_cash_fund_id' => $replacement->getKey(),
                            'error' => $exception->getMessage(),
                        ]);
                    }

                    Notification::make()
                        ->title('Reposición solicitada')
                        ->body('Transferir: '
                            .PettyCashMoney::formatUsd($replacement->transferAmountUsd())
                            .' / '
                            .PettyCashMoney::formatVes($replacement->transferAmountVes())
                            .'. Total en sistema tras aprobar: '
                            .PettyCashMoney::formatUsd((float) $replacement->amount_usd)
                            .' / '
                            .PettyCashMoney::formatVes((float) $replacement->amount_ves)
                            .'.')
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()->title('No se pudo solicitar la reposición')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function canApprove(PettyCashFund $record): bool
    {
        $user = PettyCashFundResource::currentUser();

        return $user instanceof User
            && $user->isAdministrator()
            && $record->status === PettyCashFundStatus::PendingApproval;
    }

    private static function canRegisterExpense(PettyCashFund $record): bool
    {
        $user = PettyCashFundResource::currentUser();

        return $user instanceof User
            && $user->canManagePettyCash()
            && $record->canRegisterExpenses();
    }

    private static function canReplenish(PettyCashFund $record): bool
    {
        $user = PettyCashFundResource::currentUser();

        return $user instanceof User
            && $user->canManagePettyCash()
            && $record->canReplenish();
    }

    private static function actor(): User
    {
        $user = PettyCashFundResource::currentUser();
        if (! $user instanceof User) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        return $user;
    }

    private static function storedUploadPath(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return is_string($value) ? trim($value) : '';
    }
}
