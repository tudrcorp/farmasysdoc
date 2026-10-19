<?php

namespace App\Support\Finance;

use App\Enums\PaymentBankAccountType;
use App\Models\PurchaseHistory;
use App\Support\Purchases\PurchaseHistoryEntryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Impide reutilizar la referencia de un pago de CxP ya registrado desde el mismo banco de origen.
 *
 * - La comparación ignora espacios y mayúsculas.
 * - Un pago masivo comparte la referencia entre sus facturas: se valida una sola vez antes de insertar.
 * - Pagos antiguos sin banco registrado cuentan como conflicto (no se puede descartar que sea el mismo banco).
 */
final class AccountsPayablePaymentReferenceGuard
{
    /**
     * @throws ValidationException
     */
    public static function assertUnused(
        mixed $reference,
        mixed $accountType,
        mixed $bankCode,
        mixed $bankName,
    ): void {
        $message = self::conflictMessage($reference, $accountType, $bankCode, $bankName);

        if ($message !== null) {
            throw ValidationException::withMessages(['payment_reference' => $message]);
        }
    }

    public static function conflictMessage(
        mixed $reference,
        mixed $accountType,
        mixed $bankCode,
        mixed $bankName,
    ): ?string {
        $normalizedReference = self::normalizeReference($reference);

        if ($normalizedReference === null) {
            return null;
        }

        $query = PurchaseHistory::query()
            ->where('entry_type', PurchaseHistoryEntryType::PAGO_CUENTA_POR_PAGAR)
            ->whereRaw("LOWER(REPLACE(payment_reference, ' ', '')) = ?", [$normalizedReference]);

        self::scopeToSameBank($query, $accountType, $bankCode, $bankName);

        $conflicts = $query
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get(['id', 'supplier_invoice_number', 'supplier_name', 'paid_at', 'payment_bank_account_type', 'payment_bank_code', 'payment_bank_name']);

        if ($conflicts->isEmpty()) {
            return null;
        }

        /** @var PurchaseHistory $first */
        $first = $conflicts->first();
        $others = $conflicts->count() - 1;
        $firstBankName = $first->paymentBankName();

        return sprintf(
            'La referencia «%s» ya se usó el %s en el pago de la factura %s (%s)%s%s.',
            trim((string) $reference),
            $first->paid_at?->format('d/m/Y') ?? '—',
            filled($first->supplier_invoice_number) ? $first->supplier_invoice_number : '#'.$first->getKey(),
            filled($first->supplier_name) ? $first->supplier_name : 'proveedor sin nombre',
            $firstBankName !== null ? ' desde '.$firstBankName : '',
            $others > 0 ? ' y en '.$others.' factura(s) más' : '',
        );
    }

    /**
     * @param  Builder<PurchaseHistory>  $query
     */
    private static function scopeToSameBank(Builder $query, mixed $accountType, mixed $bankCode, mixed $bankName): void
    {
        $type = $accountType instanceof PaymentBankAccountType
            ? $accountType
            : PaymentBankAccountType::tryFrom((string) $accountType);

        if ($type === null) {
            return;
        }

        if ($type->requiresManualBankName()) {
            $normalizedBankName = mb_strtolower(trim((string) $bankName));

            if ($normalizedBankName === '') {
                return;
            }

            $query->where(function (Builder $query) use ($normalizedBankName): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('payment_bank_code')->whereNull('payment_bank_name');
                })->orWhereRaw('LOWER(TRIM(payment_bank_name)) = ?', [$normalizedBankName]);
            });

            return;
        }

        if (! filled($bankCode)) {
            return;
        }

        $query->where(function (Builder $query) use ($bankCode): void {
            $query->where(function (Builder $query): void {
                $query->whereNull('payment_bank_code')->whereNull('payment_bank_name');
            })->orWhere('payment_bank_code', (string) $bankCode);
        });
    }

    private static function normalizeReference(mixed $reference): ?string
    {
        if (! filled($reference)) {
            return null;
        }

        $normalized = mb_strtolower(str_replace(' ', '', trim((string) $reference)));

        return $normalized === '' ? null : $normalized;
    }
}
