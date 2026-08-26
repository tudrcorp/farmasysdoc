<?php

namespace App\Support\Sales;

use App\Models\ConciliationCachea;
use App\Models\Sale;

/**
 * Atribuye el cobro real de una venta: VES queda en VES, USD queda en USD.
 * Cashea solo aporta la cuota/inicial, según cómo se cobró, sin el financiamiento.
 */
final class SaleCollectedMoneyAttributor
{
    /**
     * @return list<string>
     */
    public static function vesPaymentMethods(): array
    {
        return ['punto_venta_ves', 'pago_movil', 'efectivo_ves', 'transfer_ves'];
    }

    /**
     * @return list<string>
     */
    public static function usdPaymentMethods(): array
    {
        return ['efectivo_usd', 'zelle', 'transfer_usd'];
    }

    public function attribute(Sale $sale): SaleCollectedMoney
    {
        $method = (string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? '');
        $terminalId = filled($sale->pos_terminal_id) ? (int) $sale->pos_terminal_id : null;
        $paymentUsd = round((float) $sale->payment_usd, 2);
        $paymentVes = round((float) $sale->payment_ves, 2);

        return match ($method) {
            'punto_venta_ves' => new SaleCollectedMoney(
                posVes: $paymentVes,
                posTerminalId: $paymentVes > 0.00001 ? $terminalId : null,
            ),
            'pago_movil' => new SaleCollectedMoney(pagoMovilVes: $paymentVes),
            'transfer_ves' => new SaleCollectedMoney(transferVes: $paymentVes),
            'efectivo_ves' => new SaleCollectedMoney(efectivoVes: $paymentVes),
            'efectivo_usd' => new SaleCollectedMoney(efectivoUsd: $paymentUsd),
            'zelle', 'transfer_usd' => new SaleCollectedMoney(transferUsd: $paymentUsd),
            'mixed' => $this->attributeMixed($sale, $paymentUsd, $paymentVes, $terminalId),
            PosPaymentMethodOptions::CACHEA => $this->attributeCacheaCuota($sale, $paymentUsd, $paymentVes, $terminalId),
            default => SaleCollectedMoney::empty(),
        };
    }

    /**
     * Cobro de la venta en las monedas reales, para desgloses por método registrado.
     *
     * @return array{usd: float, ves: float}
     */
    public function collectedPair(Sale $sale): array
    {
        $money = $this->attribute($sale);

        return [
            'usd' => $money->usdTotal(),
            'ves' => $money->vesTotal(),
        ];
    }

    /**
     * @return array{
     *     channel: string,
     *     cuota_usd: float,
     *     collected_usd: float,
     *     collected_ves: float,
     *     remainder_usd: float
     * }|null
     */
    public function cacheaCuotaSnapshot(Sale $sale): ?array
    {
        $method = (string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? '');
        if ($method !== PosPaymentMethodOptions::CACHEA) {
            return null;
        }

        $cachea = $sale->conciliationCachea;
        $terminalId = filled($sale->pos_terminal_id) ? (int) $sale->pos_terminal_id : null;
        $channel = $this->resolveCacheaCuotaChannel($sale, $cachea, $terminalId);
        $money = $this->attributeCacheaCuota(
            $sale,
            round((float) $sale->payment_usd, 2),
            round((float) $sale->payment_ves, 2),
            $terminalId,
        );

        return [
            'channel' => $channel,
            'cuota_usd' => round((float) ($cachea?->cachea_paid_amount ?? $sale->payment_usd), 2),
            'collected_usd' => $money->usdTotal(),
            'collected_ves' => $money->vesTotal(),
            'remainder_usd' => round((float) ($cachea?->remainder ?? 0), 2),
        ];
    }

    private function attributeMixed(Sale $sale, float $paymentUsd, float $paymentVes, ?int $terminalId): SaleCollectedMoney
    {
        $split = MixedPosPaymentSupport::vesSplitAmountsFromSaleNotes($sale->notes);
        if ($split !== null) {
            return new SaleCollectedMoney(
                pagoMovilVes: $split['pago_movil'],
                posVes: $split['punto_venta_ves'],
                posTerminalId: $split['punto_venta_ves'] > 0.00001 ? $terminalId : null,
                transferVes: $split['transfer_ves'],
                efectivoVes: $split['efectivo_ves'],
                efectivoUsd: $paymentUsd,
            );
        }

        $reference = (string) ($sale->reference ?? '');
        $vesLooksLikePos = $terminalId !== null || str_contains(mb_strtoupper($reference), 'POS');

        return new SaleCollectedMoney(
            pagoMovilVes: (! $vesLooksLikePos && $this->referenceLooksLikePagoMovil($reference))
                ? $paymentVes
                : 0.0,
            posVes: $vesLooksLikePos ? $paymentVes : 0.0,
            posTerminalId: $vesLooksLikePos && $paymentVes > 0.00001 ? $terminalId : null,
            efectivoVes: (! $vesLooksLikePos && ! $this->referenceLooksLikePagoMovil($reference))
                ? $paymentVes
                : 0.0,
            efectivoUsd: $paymentUsd,
        );
    }

    private function attributeCacheaCuota(Sale $sale, float $paymentUsd, float $paymentVes, ?int $terminalId): SaleCollectedMoney
    {
        $cachea = $sale->conciliationCachea;
        $cuotaUsd = round((float) ($cachea?->cachea_paid_amount ?? $paymentUsd), 2);
        $channel = $this->resolveCacheaCuotaChannel($sale, $cachea, $terminalId);

        if (in_array($channel, self::vesPaymentMethods(), true)) {
            $cuotaVes = $paymentVes > 0.00001 ? $paymentVes : 0.0;

            return match ($channel) {
                'punto_venta_ves' => new SaleCollectedMoney(
                    posVes: $cuotaVes,
                    posTerminalId: $cuotaVes > 0.00001 ? $terminalId : null,
                ),
                'pago_movil' => new SaleCollectedMoney(pagoMovilVes: $cuotaVes),
                'transfer_ves' => new SaleCollectedMoney(transferVes: $cuotaVes),
                default => new SaleCollectedMoney(efectivoVes: $cuotaVes),
            };
        }

        $cuotaUsd = $cuotaUsd > 0.00001 ? $cuotaUsd : $paymentUsd;

        return match ($channel) {
            'zelle', 'transfer_usd' => new SaleCollectedMoney(transferUsd: $cuotaUsd),
            default => new SaleCollectedMoney(efectivoUsd: $cuotaUsd),
        };
    }

    private function resolveCacheaCuotaChannel(Sale $sale, ?ConciliationCachea $cachea, ?int $terminalId): string
    {
        $complement = (string) ($cachea?->complement_payment_method ?? '');
        $reference = mb_strtolower(trim((string) ($sale->reference ?? $cachea?->reference ?? '')));

        if (in_array($complement, self::usdPaymentMethods(), true)) {
            return $complement;
        }

        if ($complement === 'punto_venta_ves'
            || $terminalId !== null
            || str_contains($reference, 'pos')) {
            return 'punto_venta_ves';
        }

        if (in_array($complement, ['pago_movil', 'efectivo_ves', 'transfer_ves'], true)) {
            return $complement;
        }

        $paymentUsd = round((float) $sale->payment_usd, 2);
        $paymentVes = round((float) $sale->payment_ves, 2);

        if ($paymentVes > 0.00001 && $paymentUsd <= 0.00001) {
            return 'efectivo_ves';
        }

        return 'efectivo_usd';
    }

    private function referenceLooksLikePagoMovil(string $reference): bool
    {
        $normalized = mb_strtolower(trim($reference));

        return $normalized !== ''
            && (str_contains($normalized, 'pago movil')
                || str_contains($normalized, 'pago_movil')
                || preg_match('/^\d{4,12}$/', $normalized) === 1);
    }
}
