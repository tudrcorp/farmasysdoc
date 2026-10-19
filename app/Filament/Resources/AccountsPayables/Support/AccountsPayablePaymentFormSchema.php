<?php

namespace App\Filament\Resources\AccountsPayables\Support;

use App\Enums\PaymentBankAccountType;
use App\Enums\VenezuelanPagoMovilBank;
use App\Models\AccountsPayable;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\AccountsPayablePaymentReferenceGuard;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Campos comunes del modal de registro de pago (CxP individual o masivo).
 */
final class AccountsPayablePaymentFormSchema
{
    /**
     * @return list<Component>
     */
    public static function paymentFields(bool $includeAmountInputs = true, bool $includeOptionalPaymentProof = false): array
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
            ...self::paymentBankFields(),
            TextInput::make('payment_reference')
                ->label('Referencia del pago (opcional)')
                ->maxLength(255)
                ->placeholder('Nº de operación, lote, etc.')
                ->helperText('Algunos medios no generan referencia; puede dejarlo vacío. No puede repetirse en otro pago desde el mismo banco.')
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $message = AccountsPayablePaymentReferenceGuard::conflictMessage(
                        $value,
                        $get('payment_bank_account_type'),
                        $get('payment_bank_code'),
                        $get('payment_bank_name'),
                    );

                    if ($message !== null) {
                        $fail($message);
                    }
                }),
            Textarea::make('notes')
                ->label('Notas (opcional)')
                ->rows(2)
                ->maxLength(2000),
        ];

        if ($includeOptionalPaymentProof) {
            $tail[] = self::paymentProofUpload(
                required: false,
                helperText: $includeAmountInputs
                    ? 'Opcional. JPG, PNG, WebP o PDF. Máximo 5 MB. Puede registrar el pago sin adjuntar archivo.'
                    : 'Opcional. JPG, PNG, WebP o PDF. Máximo 5 MB. Si lo carga, se asocia a todas las cuentas de este pago.',
            );
        }

        return array_merge($base, $amounts, $tail);
    }

    /**
     * Banco y tipo de cuenta de Farmadoc desde la que se paga.
     *
     * @return list<Component>
     */
    public static function paymentBankFields(): array
    {
        $requiresManualBankName = static fn (Get $get): bool => PaymentBankAccountType::tryFrom(
            (string) $get('payment_bank_account_type'),
        )?->requiresManualBankName() ?? false;

        return [
            Select::make('payment_bank_code')
                ->label('Banco de origen (Farmadoc)')
                ->options(self::bankOptions())
                ->searchable()
                ->required()
                ->native(false)
                ->visible(fn (Get $get): bool => ! $requiresManualBankName($get)),
            TextInput::make('payment_bank_name')
                ->label('Nombre del banco')
                ->placeholder('Ej.: Zinli, Wally, Bank of America…')
                ->required()
                ->maxLength(120)
                ->visible($requiresManualBankName),
            ToggleButtons::make('payment_bank_account_type')
                ->label('Tipo de cuenta')
                ->options(PaymentBankAccountType::options())
                ->default(PaymentBankAccountType::Checking->value)
                ->inline()
                ->required()
                ->live(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function bankOptions(): array
    {
        $options = [];

        foreach (VenezuelanPagoMovilBank::cases() as $bank) {
            $options[$bank->value] = $bank->optionLabel();
        }

        return $options;
    }

    /**
     * @return array{payment_bank_account_type: string, payment_bank_code: string, payment_bank_name: null}
     */
    public static function defaultPaymentBankState(): array
    {
        return [
            'payment_bank_account_type' => PaymentBankAccountType::Checking->value,
            'payment_bank_code' => VenezuelanPagoMovilBank::Banesco->value,
            'payment_bank_name' => null,
        ];
    }

    public static function paymentProofUpload(bool $required = false, ?string $helperText = null): FileUpload
    {
        $upload = FileUpload::make('payment_proof_path')
            ->label($required ? 'Archivo del comprobante' : 'Documento del pago')
            ->helperText($helperText ?? ($required
                ? 'Formatos: JPG, PNG, WebP o PDF. Máximo 5 MB.'
                : 'Opcional. JPG, PNG, WebP o PDF. Máximo 5 MB. Puede registrar el pago sin adjuntar archivo.'))
            ->disk('public')
            ->directory('accounts-payables/payment-proofs')
            ->visibility('public')
            ->acceptedFileTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/pdf',
            ])
            ->maxSize(5120)
            ->downloadable()
            ->openable()
            ->columnSpanFull();

        if ($required) {
            $upload->required();
        }

        return $upload;
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
            ...self::defaultPaymentBankState(),
            'payment_reference' => '',
            'notes' => null,
            'payment_proof_path' => null,
        ];
    }
}
