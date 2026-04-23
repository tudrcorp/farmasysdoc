<?php

namespace App\Filament\Resources\AccountsPayables\Support;

use App\Models\AccountsPayable;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Campos comunes del modal de registro de pago (CxP individual o masivo).
 */
final class AccountsPayablePaymentFormSchema
{
    /**
     * @return list<Component>
     */
    public static function paymentFields(bool $includeAmountInputs = true): array
    {
        $base = [
            Select::make('payment_method')
                ->label('Método de pago')
                ->options(PurchaseHistoryPaymentMethod::options())
                ->required()
                ->native(false),
            Select::make('payment_form')
                ->label('Forma de pago')
                ->options(PurchaseHistoryPaymentForm::options())
                ->required()
                ->native(false),
            DateTimePicker::make('paid_at')
                ->label('Fecha y hora del pago')
                ->seconds(false)
                ->default(now())
                ->required(),
        ];

        $amounts = $includeAmountInputs ? [
            TextInput::make('amount_paid_usd')
                ->label('Total pagado (USD)')
                ->numeric()
                ->minValue(0.01)
                ->step(0.01)
                ->required(),
            TextInput::make('amount_paid_ves')
                ->label('Total pagado (Bs, tasa BCV del día actual)')
                ->numeric()
                ->minValue(0.01)
                ->step(0.01)
                ->required()
                ->helperText('Debe cuadrar con el monto en USD usando el promedio oficial Bs/USD del día en curso (misma tasa que usa el sistema para validar).'),
        ] : [];

        $tail = [
            TextInput::make('payment_reference')
                ->label('Referencia del pago (opcional)')
                ->maxLength(255)
                ->placeholder('Nº de operación, lote, etc.')
                ->helperText('Algunos medios no generan referencia; puede dejarlo vacío.'),
            Textarea::make('notes')
                ->label('Notas (opcional)')
                ->rows(2)
                ->maxLength(2000),
        ];

        return array_merge($base, $amounts, $tail);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultStateForRecord(AccountsPayable $record): array
    {
        $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now()->startOfDay()) ?? 0.0;
        $remaining = round((float) ($record->remaining_principal_usd ?? $record->purchase_total_usd), 2);
        $ves = $rate > 0 ? round($remaining * (float) $rate, 2) : 0.0;

        return [
            'payment_method' => PurchaseHistoryPaymentMethod::TRANSFERENCIA,
            'payment_form' => PurchaseHistoryPaymentForm::LIQUIDACION_TOTAL,
            'paid_at' => now(),
            'amount_paid_usd' => $remaining,
            'amount_paid_ves' => $ves,
            'payment_reference' => '',
            'notes' => null,
        ];
    }
}
