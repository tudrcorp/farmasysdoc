<?php

namespace App\Filament\Resources\AccountsPayables\Support;

use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Forms\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Modal de pago masivo: el detalle de facturas va en una vista HTML,
 * y el formulario solo guarda los datos de pago compartidos.
 */
final class AccountsPayableBulkPaymentFormSchema
{
    /**
     * @return list<Component|\Filament\Schemas\Components\Component>
     */
    public static function modalSchema(): array
    {
        return [
            Section::make('Datos del pago')
                ->description('Se aplican a todas las cuentas seleccionadas.')
                ->icon(Heroicon::Banknotes)
                ->compact()
                ->schema(AccountsPayablePaymentFormSchema::paymentFields(false, true))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultFormState(): array
    {
        return [
            'payment_method' => PurchaseHistoryPaymentMethod::TRANSFERENCIA,
            'payment_form' => PurchaseHistoryPaymentForm::LIQUIDACION_TOTAL,
            'paid_at' => now(),
            'payment_reference' => '',
            'notes' => null,
            'payment_proof_path' => null,
        ];
    }
}
