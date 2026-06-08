<?php

namespace App\Services\Sales;

use App\Enums\ConciliationCacheaCollectionStatus;
use App\Models\ConciliationCachea;
use App\Models\Sale;
use App\Models\User;
use App\Support\Sales\CacheaPosPaymentSupport;
use App\Support\Sales\PosPaymentMethodOptions;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class CacheaConciliationRegistrar
{
    /**
     * @param  array{
     *     cachea_paid_amount: float,
     *     remainder: float,
     *     complement_payment_method?: string|null,
     *     reference?: string|null,
     * }  $payload
     */
    public static function register(Sale $sale, array $payload, ?User $user = null): ConciliationCachea
    {
        $user ??= Auth::user();
        $actor = $user?->email ?? $user?->name ?? 'sistema';

        $record = ConciliationCachea::query()->firstOrNew(['sale_id' => (int) $sale->id]);

        $record->fill([
            'branch_id' => (int) $sale->branch_id,
            'user_id' => $user?->id,
            'sale_number' => (string) $sale->sale_number,
            'sale_total' => round((float) $sale->total, 2),
            'cachea_paid_amount' => round((float) $payload['cachea_paid_amount'], 2),
            'remainder' => round((float) $payload['remainder'], 2),
            'complement_payment_method' => filled($payload['complement_payment_method'] ?? null)
                ? (string) $payload['complement_payment_method']
                : null,
            'reference' => filled($payload['reference'] ?? null) ? (string) $payload['reference'] : null,
            'recorded_at' => now(),
            'created_by' => $actor,
        ]);

        if (! $record->exists) {
            $record->collection_status = ConciliationCacheaCollectionStatus::PendingCollection;
        }

        $record->save();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function registerFromPosSale(
        Sale $sale,
        array $data,
        float $vesUsdRate,
        ?string $paymentReference = null,
        ?User $user = null,
    ): ConciliationCachea {
        if (! PosPaymentMethodOptions::isCachea($sale->payment_method)) {
            throw new RuntimeException('La venta no está marcada como pago Cachea.');
        }

        $cacheaPaid = CacheaPosPaymentSupport::paidAmountFromData($data);
        if ($cacheaPaid <= 0.00001) {
            throw new RuntimeException('No se registró la conciliación Cachea: falta el monto pagado con Cachea.');
        }

        $documentTotal = round((float) $sale->total, 2);
        if ($cacheaPaid > $documentTotal + 0.02) {
            throw new RuntimeException('No se registró la conciliación Cachea: el monto Cachea supera el total de la venta.');
        }

        $breakdown = CacheaPosPaymentSupport::breakdown($documentTotal, $data, $vesUsdRate);

        return self::register($sale, [
            'cachea_paid_amount' => $breakdown['cachea_paid_amount'],
            'remainder' => $breakdown['remainder'],
            'complement_payment_method' => $breakdown['remainder'] > 0.00001
                ? $breakdown['complement_payment_method']
                : null,
            'reference' => filled($paymentReference) ? $paymentReference : null,
        ], $user);
    }
}
