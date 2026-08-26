<?php

namespace App\Support\Sales;

use App\Models\Sale;
use Illuminate\Support\Collection;

final class SaleCollectedMoneyAggregator
{
    public function __construct(
        private readonly SaleCollectedMoneyAttributor $attributor,
        private readonly PosTerminalCloseLines $posTerminalCloseLines,
    ) {}

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array{
     *     sale_count: int,
     *     total_usd: float,
     *     total_ves: float,
     *     pago_movil_ves: float,
     *     punto_venta_ves: float,
     *     pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }
     */
    public function closeTotals(Collection $sales, ?int $branchId): array
    {
        $totalsByTerminalId = [];
        $unassignedPosVes = 0.0;
        $combined = SaleCollectedMoney::empty();

        foreach ($sales as $sale) {
            $money = $this->attributor->attribute($sale);
            $combined = $combined->add($money);

            if ($money->posVes <= 0.00001) {
                continue;
            }

            $terminalId = $money->posTerminalId;
            if ($terminalId === null) {
                $unassignedPosVes = round($unassignedPosVes + $money->posVes, 2);

                continue;
            }

            $totalsByTerminalId[$terminalId] = round(($totalsByTerminalId[$terminalId] ?? 0.0) + $money->posVes, 2);
        }

        $posTerminals = $this->posTerminalCloseLines->build($branchId, $totalsByTerminalId, $unassignedPosVes);
        $puntoVentaVes = round((float) collect($posTerminals)->sum('amount_ves'), 2);

        return [
            'sale_count' => $sales->count(),
            'total_usd' => $combined->usdTotal(),
            'total_ves' => $combined->vesTotal(),
            'pago_movil_ves' => $combined->pagoMovilVes,
            'punto_venta_ves' => $puntoVentaVes,
            'pos_terminals' => $posTerminals,
            'transfer_ves' => $combined->transferVes,
            'transfer_usd' => $combined->transferUsd,
            'efectivo_ves' => $combined->efectivoVes,
            'efectivo_usd' => $combined->efectivoUsd,
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array{usd: float, ves: float}
     */
    public function collectedTotals(Collection $sales): array
    {
        $usd = 0.0;
        $ves = 0.0;

        foreach ($sales as $sale) {
            $pair = $this->attributor->collectedPair($sale);
            $usd = round($usd + $pair['usd'], 2);
            $ves = round($ves + $pair['ves'], 2);
        }

        return [
            'usd' => $usd,
            'ves' => $ves,
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<string, array{usd: float, ves: float, count: int}>
     */
    public function collectedByRecordedMethod(Collection $sales): array
    {
        $map = [];

        foreach ($sales as $sale) {
            $method = strtolower(trim((string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? '')));
            if ($method === '') {
                $method = '__empty';
            }

            $pair = $this->attributor->collectedPair($sale);
            $current = $map[$method] ?? ['usd' => 0.0, 'ves' => 0.0, 'count' => 0];
            $map[$method] = [
                'usd' => round($current['usd'] + $pair['usd'], 2),
                'ves' => round($current['ves'] + $pair['ves'], 2),
                'count' => $current['count'] + 1,
            ];
        }

        return $map;
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<string, array{usd: float, ves: float, count: int}>
     */
    public function collectedByChannel(Collection $sales): array
    {
        $map = [];

        foreach ($sales as $sale) {
            $money = $this->attributor->attribute($sale);
            $this->addChannel($map, 'pago_movil', 0.0, $money->pagoMovilVes);
            $this->addChannel($map, 'punto_venta_ves', 0.0, $money->posVes);
            $this->addChannel($map, 'transfer_ves', 0.0, $money->transferVes);
            $this->addChannel($map, 'efectivo_ves', 0.0, $money->efectivoVes);
            $this->addChannel($map, 'efectivo_usd', $money->efectivoUsd, 0.0);

            if ($money->transferUsd > 0.00001) {
                $this->addChannel($map, $this->usdTransferChannel($sale), $money->transferUsd, 0.0);
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return list<array{method: string, label: string, count: int, payment_usd: float, payment_ves: float}>
     */
    public function collectedChannelRows(Collection $sales): array
    {
        $map = $this->collectedByChannel($sales);
        $rows = [];

        foreach ($this->orderedChannelKeys(array_keys($map)) as $key) {
            $totals = $map[$key];
            $rows[] = [
                'method' => $key,
                'label' => SalePaymentMethodLabels::label($key),
                'count' => (int) $totals['count'],
                'payment_usd' => $totals['usd'],
                'payment_ves' => $totals['ves'],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function orderedChannelKeys(array $keys): array
    {
        $preferred = [
            'efectivo_usd',
            'zelle',
            'transfer_usd',
            'efectivo_ves',
            'punto_venta_ves',
            'pago_movil',
            'transfer_ves',
        ];
        $ordered = [];

        foreach ($preferred as $key) {
            if (in_array($key, $keys, true)) {
                $ordered[] = $key;
            }
        }

        $remaining = array_diff($keys, $ordered);
        sort($remaining);

        return array_merge($ordered, array_values($remaining));
    }

    /**
     * Cuotas Cashea por canal de cobro. No se suman otra vez a los totales: ya están en el canal.
     *
     * @param  Collection<int, Sale>  $sales
     * @return array{
     *     sale_count: int,
     *     cuota_usd: float,
     *     collected_usd: float,
     *     collected_ves: float,
     *     remainder_usd: float,
     *     channels: list<array{
     *         channel: string,
     *         label: string,
     *         count: int,
     *         cuota_usd: float,
     *         collected_usd: float,
     *         collected_ves: float
     *     }>
     * }
     */
    public function cacheaCuotaBreakdown(Collection $sales): array
    {
        $channels = [];
        $saleCount = 0;
        $cuotaUsd = 0.0;
        $collectedUsd = 0.0;
        $collectedVes = 0.0;
        $remainderUsd = 0.0;

        foreach ($sales as $sale) {
            $snapshot = $this->attributor->cacheaCuotaSnapshot($sale);
            if ($snapshot === null) {
                continue;
            }

            $saleCount++;
            $cuotaUsd = round($cuotaUsd + $snapshot['cuota_usd'], 2);
            $collectedUsd = round($collectedUsd + $snapshot['collected_usd'], 2);
            $collectedVes = round($collectedVes + $snapshot['collected_ves'], 2);
            $remainderUsd = round($remainderUsd + $snapshot['remainder_usd'], 2);

            $channel = $snapshot['channel'];
            $current = $channels[$channel] ?? [
                'channel' => $channel,
                'label' => SalePaymentMethodLabels::label($channel),
                'count' => 0,
                'cuota_usd' => 0.0,
                'collected_usd' => 0.0,
                'collected_ves' => 0.0,
            ];
            $channels[$channel] = [
                'channel' => $channel,
                'label' => $current['label'],
                'count' => $current['count'] + 1,
                'cuota_usd' => round($current['cuota_usd'] + $snapshot['cuota_usd'], 2),
                'collected_usd' => round($current['collected_usd'] + $snapshot['collected_usd'], 2),
                'collected_ves' => round($current['collected_ves'] + $snapshot['collected_ves'], 2),
            ];
        }

        $ordered = [];
        foreach ($this->orderedChannelKeys(array_keys($channels)) as $key) {
            $ordered[] = $channels[$key];
        }

        return [
            'sale_count' => $saleCount,
            'cuota_usd' => $cuotaUsd,
            'collected_usd' => $collectedUsd,
            'collected_ves' => $collectedVes,
            'remainder_usd' => $remainderUsd,
            'channels' => $ordered,
        ];
    }

    /**
     * @param  array<string, array{usd: float, ves: float, count: int}>  $map
     */
    private function addChannel(array &$map, string $key, float $usd, float $ves): void
    {
        if ($usd <= 0.00001 && $ves <= 0.00001) {
            return;
        }

        $current = $map[$key] ?? ['usd' => 0.0, 'ves' => 0.0, 'count' => 0];
        $map[$key] = [
            'usd' => round($current['usd'] + $usd, 2),
            'ves' => round($current['ves'] + $ves, 2),
            'count' => $current['count'] + 1,
        ];
    }

    private function usdTransferChannel(Sale $sale): string
    {
        $method = (string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? '');

        if ($method === 'transfer_usd') {
            return 'transfer_usd';
        }

        if ($method === PosPaymentMethodOptions::CACHEA) {
            $complement = (string) ($sale->conciliationCachea?->complement_payment_method ?? '');
            if ($complement === 'transfer_usd') {
                return 'transfer_usd';
            }
        }

        return 'zelle';
    }
}
