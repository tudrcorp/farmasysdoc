<?php

namespace App\Services\Finance;

use App\Models\AccountsReceivable;
use App\Models\Sale;
use App\Support\Finance\AccountsReceivableStatus;
use Illuminate\Support\Carbon;

/**
 * Crea o actualiza el registro de cuenta por cobrar asociado a una venta (p. ej. venta a crédito en caja).
 */
final class AccountsReceivableFromSaleRegistrar
{
    public static function register(Sale $sale, string $actor): AccountsReceivable
    {
        $sale->loadMissing(['client', 'branch']);

        $totalUsd = round(max(0.0, (float) $sale->total), 2);
        $paidUsd = round(max(0.0, (float) $sale->payment_usd), 2);
        $paidVes = round(max(0.0, (float) $sale->payment_ves), 2);
        $rate = ($sale->bcv_ves_per_usd !== null && (float) $sale->bcv_ves_per_usd > 0)
            ? (float) $sale->bcv_ves_per_usd
            : 0.0;
        $vesPortionUsd = ($rate > 0.0) ? round($paidVes / $rate, 2) : 0.0;
        $paidEquivalentUsd = round(min($totalUsd, $paidUsd + $vesPortionUsd), 2);
        $remainingPrincipalUsd = round(max(0.0, $totalUsd - $paidEquivalentUsd), 2);

        $issuedAt = Carbon::parse($sale->sold_at ?? now())->startOfDay();

        $bcv = ($sale->bcv_ves_per_usd !== null && (float) $sale->bcv_ves_per_usd > 0)
            ? (float) $sale->bcv_ves_per_usd
            : null;

        $saleTotalVesRef = ($bcv !== null && $bcv > 0)
            ? round($totalUsd * $bcv, 2)
            : 0.0;

        $originalBalanceVes = ($bcv !== null && $bcv > 0)
            ? round($remainingPrincipalUsd * $bcv, 2)
            : 0.0;

        $status = $remainingPrincipalUsd < 0.01
            ? AccountsReceivableStatus::COBRADO
            : AccountsReceivableStatus::POR_COBRAR;

        $client = $sale->client;

        return AccountsReceivable::query()->updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'branch_id' => $sale->branch_id,
                'client_id' => $sale->client_id,
                'status' => $status,
                'issued_at' => $issuedAt->toDateString(),
                'due_at' => $issuedAt->copy()->addDays(30)->toDateString(),
                'sale_number_snapshot' => (string) $sale->sale_number,
                'client_name_snapshot' => $client?->name ?? 'Cliente',
                'client_document_snapshot' => $client?->document_number,
                'sale_total_usd' => $totalUsd,
                'paid_equivalent_usd' => $paidEquivalentUsd,
                'remaining_principal_usd' => $remainingPrincipalUsd,
                'payment_usd_snapshot' => $paidUsd,
                'payment_ves_snapshot' => $paidVes,
                'bcv_ves_per_usd_snapshot' => $bcv,
                'sale_total_ves_reference' => $saleTotalVesRef,
                'original_balance_ves' => $originalBalanceVes,
                'current_balance_ves' => $originalBalanceVes,
                'last_balance_recalculated_at' => now(),
                'notes' => null,
                'created_by' => $actor,
                'updated_by' => $actor,
            ],
        );
    }
}
