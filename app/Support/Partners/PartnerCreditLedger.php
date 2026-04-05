<?php

namespace App\Support\Partners;

use App\Enums\OrderPartnerPaymentTerms;
use App\Enums\OrderStatus;
use App\Models\HistoricalOfMovement;
use App\Models\Order;
use App\Models\PartnerCompany;
use Illuminate\Support\Facades\DB;

/**
 * Registra el consumo de crédito del aliado cuando un pedido a crédito pasa a «En proceso».
 */
final class PartnerCreditLedger
{
    /**
     * Idempotente por pedido: como máximo un movimiento por `order_id`.
     *
     * @throws InsufficientPartnerCreditException
     */
    public static function recordConsumptionIfApplicable(Order $order): void
    {
        if ($order->partner_company_id === null) {
            return;
        }

        if ($order->partner_payment_terms !== OrderPartnerPaymentTerms::Credit) {
            return;
        }

        if ($order->status !== OrderStatus::InProgress) {
            return;
        }

        $orderTotal = round((float) $order->total, 2);

        if ($orderTotal <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $orderTotal): void {
            if (HistoricalOfMovement::query()->where('order_id', $order->id)->exists()) {
                return;
            }

            $company = PartnerCompany::query()
                ->whereKey($order->partner_company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balance = round((float) $company->assigned_credit_limit, 2);

            if ($balance <= 0) {
                throw new InsufficientPartnerCreditException(
                    'La compañía aliada no tiene saldo de crédito disponible; no se puede poner este pedido a crédito en proceso.'
                );
            }

            if ($orderTotal > $balance) {
                throw new InsufficientPartnerCreditException(
                    sprintf(
                        'Crédito insuficiente. Disponible: %s. Monto del pedido: %s.',
                        number_format($balance, 2, ',', '.'),
                        number_format($orderTotal, 2, ',', '.')
                    )
                );
            }

            $newBalance = round($balance - $orderTotal, 2);

            $qty = (float) $order->items()->sum('quantity');

            HistoricalOfMovement::query()->create([
                'order_id' => $order->id,
                'partner_company_id' => $company->id,
                'total_quantity_products' => $qty,
                'total_cost' => $orderTotal,
                'remaining_credit' => $newBalance,
            ]);

            $company->forceFill([
                'assigned_credit_limit' => $newBalance,
            ])->save();
        });
    }
}
