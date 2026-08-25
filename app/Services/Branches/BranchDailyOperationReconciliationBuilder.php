<?php

namespace App\Services\Branches;

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Support\Sales\MixedPosPaymentSupport;
use App\Support\Sales\PosPaymentMethodOptions;

final class BranchDailyOperationReconciliationBuilder
{
    /**
     * @return array{
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     closed_by_name: string,
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
    public function build(Branch $branch, BranchDailyOperation $operation): array
    {
        $openedAt = $operation->opened_at ?? now();
        $closedAt = $operation->closed_at ?? now();
        $timezone = (string) config('app.timezone');

        $operation->loadMissing('closedBy');

        $sales = Sale::query()
            ->with(['posTerminal', 'conciliationCachea'])
            ->excludingInternalBranchTransfers()
            ->where('status', SaleStatus::Completed)
            ->where('branch_id', $branch->getKey())
            ->whereRaw('COALESCE(sold_at, created_at) >= ?', [$openedAt])
            ->whereRaw('COALESCE(sold_at, created_at) <= ?', [$closedAt])
            ->orderByRaw('COALESCE(sold_at, created_at) ASC')
            ->orderBy('id')
            ->get();

        $totalsByTerminalId = [];
        $unassignedPosVes = 0.0;
        $pagoMovilVes = 0.0;
        $transferVes = 0.0;
        $transferUsd = 0.0;
        $efectivoVes = 0.0;
        $efectivoUsd = 0.0;
        $totalUsd = 0.0;
        $totalVes = 0.0;

        foreach ($sales as $sale) {
            $resolved = $this->resolvedPaymentAmounts($sale);
            $totalUsd = round($totalUsd + $resolved['usd'], 2);
            $totalVes = round($totalVes + $resolved['ves'], 2);

            $attributed = $this->attributeSale($sale, $resolved);
            $pagoMovilVes = round($pagoMovilVes + $attributed['pago_movil_ves'], 2);
            $transferVes = round($transferVes + $attributed['transfer_ves'], 2);
            $transferUsd = round($transferUsd + $attributed['transfer_usd'], 2);
            $efectivoVes = round($efectivoVes + $attributed['efectivo_ves'], 2);
            $efectivoUsd = round($efectivoUsd + $attributed['efectivo_usd'], 2);

            if ($attributed['pos_ves'] <= 0.00001) {
                continue;
            }

            $terminalId = $attributed['pos_terminal_id'];
            if ($terminalId === null) {
                $unassignedPosVes = round($unassignedPosVes + $attributed['pos_ves'], 2);

                continue;
            }

            $totalsByTerminalId[$terminalId] = round(($totalsByTerminalId[$terminalId] ?? 0.0) + $attributed['pos_ves'], 2);
        }

        $posTerminals = $this->buildPosTerminalLines($branch, $totalsByTerminalId, $unassignedPosVes);
        $puntoVentaVes = round((float) collect($posTerminals)->sum('amount_ves'), 2);
        $closedBy = $operation->closedBy;

        return [
            'branch_name' => (string) ($branch->name ?? 'Sucursal'),
            'opened_at_label' => $openedAt->timezone($timezone)->format('d/m/Y H:i'),
            'closed_at_label' => $closedAt->timezone($timezone)->format('d/m/Y H:i'),
            'closed_by_name' => filled($closedBy?->name)
                ? (string) $closedBy->name
                : (string) ($closedBy?->email ?? 'Gerencia'),
            'sale_count' => $sales->count(),
            'total_usd' => $totalUsd,
            'total_ves' => $totalVes,
            'pago_movil_ves' => $pagoMovilVes,
            'punto_venta_ves' => $puntoVentaVes,
            'pos_terminals' => $posTerminals,
            'transfer_ves' => $transferVes,
            'transfer_usd' => $transferUsd,
            'efectivo_ves' => $efectivoVes,
            'efectivo_usd' => $efectivoUsd,
        ];
    }

    /**
     * @return array{usd: float, ves: float}
     */
    private function resolvedPaymentAmounts(Sale $sale): array
    {
        $usd = round((float) $sale->payment_usd, 2);
        $ves = round((float) $sale->payment_ves, 2);
        $rate = $this->resolveVesUsdRate($sale);

        if ($usd > 0.00001 && $ves <= 0.00001) {
            $ves = round($usd * $rate, 2);
        } elseif ($ves > 0.00001 && $usd <= 0.00001) {
            $usd = round($ves / $rate, 2);
        }

        return [
            'usd' => $usd,
            'ves' => $ves,
        ];
    }

    private function resolveVesUsdRate(Sale $sale): float
    {
        $stored = (float) ($sale->bcv_ves_per_usd ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $totalUsd = (float) $sale->total;
        $ves = (float) ($sale->payment_ves ?? 0);

        if ($totalUsd > 0.00001 && $ves > 0) {
            return $ves / $totalUsd;
        }

        $fallback = config('fiscal.fallback_ves_usd_rate');

        return is_numeric($fallback) && (float) $fallback > 0 ? (float) $fallback : 1.0;
    }

    /**
     * @param  array{usd: float, ves: float}  $resolved
     * @return array{
     *     pos_ves: float,
     *     pos_terminal_id: int|null,
     *     pago_movil_ves: float,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }
     */
    private function attributeSale(Sale $sale, array $resolved): array
    {
        $empty = [
            'pos_ves' => 0.0,
            'pos_terminal_id' => null,
            'pago_movil_ves' => 0.0,
            'transfer_ves' => 0.0,
            'transfer_usd' => 0.0,
            'efectivo_ves' => 0.0,
            'efectivo_usd' => 0.0,
        ];

        $method = (string) (PosPaymentMethodOptions::effectiveSalePaymentMethod($sale) ?? '');
        $terminalId = filled($sale->pos_terminal_id) ? (int) $sale->pos_terminal_id : null;

        return match ($method) {
            'punto_venta_ves' => [
                ...$empty,
                'pos_ves' => $resolved['ves'],
                'pos_terminal_id' => $terminalId,
            ],
            'pago_movil' => [
                ...$empty,
                'pago_movil_ves' => $resolved['ves'],
            ],
            'transfer_ves' => [
                ...$empty,
                'transfer_ves' => $resolved['ves'],
            ],
            'efectivo_ves' => [
                ...$empty,
                'efectivo_ves' => $resolved['ves'],
            ],
            'efectivo_usd' => [
                ...$empty,
                'efectivo_usd' => $resolved['usd'],
            ],
            'zelle', 'transfer_usd' => [
                ...$empty,
                'transfer_usd' => $resolved['usd'],
            ],
            'mixed' => $this->attributeMixedSale($sale, $resolved, $terminalId, $empty),
            PosPaymentMethodOptions::CACHEA => $this->attributeCacheaSale($sale, $resolved, $terminalId, $empty),
            default => $empty,
        };
    }

    /**
     * @param  array{usd: float, ves: float}  $resolved
     * @param  array{
     *     pos_ves: float,
     *     pos_terminal_id: int|null,
     *     pago_movil_ves: float,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }  $empty
     * @return array{
     *     pos_ves: float,
     *     pos_terminal_id: int|null,
     *     pago_movil_ves: float,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }
     */
    private function attributeMixedSale(Sale $sale, array $resolved, ?int $terminalId, array $empty): array
    {
        $split = MixedPosPaymentSupport::vesSplitAmountsFromSaleNotes($sale->notes);
        if ($split !== null) {
            return [
                ...$empty,
                'pos_ves' => $split['punto_venta_ves'],
                'pos_terminal_id' => $split['punto_venta_ves'] > 0.00001 ? $terminalId : null,
                'pago_movil_ves' => $split['pago_movil'],
                'transfer_ves' => $split['transfer_ves'],
                'efectivo_ves' => $split['efectivo_ves'],
                'efectivo_usd' => $resolved['usd'],
            ];
        }

        return [
            ...$empty,
            'pos_ves' => $terminalId !== null ? $resolved['ves'] : 0.0,
            'pos_terminal_id' => $terminalId,
            'pago_movil_ves' => ($terminalId === null && $this->referenceLooksLikePagoMovil((string) ($sale->reference ?? '')))
                ? $resolved['ves']
                : 0.0,
            'efectivo_usd' => $resolved['usd'],
            'efectivo_ves' => ($terminalId === null && ! $this->referenceLooksLikePagoMovil((string) ($sale->reference ?? '')))
                ? $resolved['ves']
                : 0.0,
        ];
    }

    /**
     * @param  array{usd: float, ves: float}  $resolved
     * @param  array{
     *     pos_ves: float,
     *     pos_terminal_id: int|null,
     *     pago_movil_ves: float,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }  $empty
     * @return array{
     *     pos_ves: float,
     *     pos_terminal_id: int|null,
     *     pago_movil_ves: float,
     *     transfer_ves: float,
     *     transfer_usd: float,
     *     efectivo_ves: float,
     *     efectivo_usd: float,
     * }
     */
    private function attributeCacheaSale(Sale $sale, array $resolved, ?int $terminalId, array $empty): array
    {
        $cachea = $sale->conciliationCachea;
        $cacheaPaid = round((float) ($cachea?->cachea_paid_amount ?? $resolved['usd']), 2);
        $complement = (string) ($cachea?->complement_payment_method ?? '');
        $remainderUsd = round((float) ($cachea?->remainder ?? 0), 2);
        $rate = $this->resolveVesUsdRate($sale);
        $complementVes = round($remainderUsd * $rate, 2);

        $attributed = [
            ...$empty,
            'efectivo_usd' => $cacheaPaid,
        ];

        if ($complement === 'punto_venta_ves') {
            $attributed['pos_ves'] = $complementVes;
            $attributed['pos_terminal_id'] = $complementVes > 0.00001 ? $terminalId : null;
        } elseif ($complement === 'pago_movil') {
            $attributed['pago_movil_ves'] = $complementVes;
        } elseif ($complement === 'transfer_ves') {
            $attributed['transfer_ves'] = $complementVes;
        } elseif ($complement === 'efectivo_ves') {
            $attributed['efectivo_ves'] = $complementVes;
        } elseif (in_array($complement, ['zelle', 'transfer_usd'], true)) {
            $attributed['transfer_usd'] = round($attributed['transfer_usd'] + $remainderUsd, 2);
        } elseif ($complement === 'efectivo_usd') {
            $attributed['efectivo_usd'] = round($attributed['efectivo_usd'] + $remainderUsd, 2);
        }

        return $attributed;
    }

    /**
     * @param  array<int, float>  $totalsByTerminalId
     * @return list<array{id: int|null, label: string, amount_ves: float}>
     */
    private function buildPosTerminalLines(Branch $branch, array $totalsByTerminalId, float $unassignedPosVes): array
    {
        $terminals = PosTerminal::query()
            ->where('branch_id', $branch->getKey())
            ->orderBy('bank_code')
            ->orderBy('code')
            ->get();

        $usedTerminalIds = array_keys($totalsByTerminalId);
        $missingIds = array_values(array_diff($usedTerminalIds, $terminals->modelKeys()));
        if ($missingIds !== []) {
            $terminals = $terminals->concat(
                PosTerminal::query()->whereIn('id', $missingIds)->get()
            );
        }

        $bankNameCounts = [];
        foreach ($terminals as $terminal) {
            $bankName = $terminal->bank()?->bankName() ?? (string) $terminal->bank_code;
            $bankNameCounts[$bankName] = ($bankNameCounts[$bankName] ?? 0) + 1;
        }

        $posTerminals = [];
        foreach ($terminals as $terminal) {
            $bankName = $terminal->bank()?->bankName() ?? (string) $terminal->bank_code;
            $label = ($bankNameCounts[$bankName] ?? 0) > 1
                ? 'POS '.$bankName.' '.$terminal->code
                : 'POS '.$bankName;

            $posTerminals[] = [
                'id' => (int) $terminal->id,
                'label' => $label,
                'amount_ves' => round((float) ($totalsByTerminalId[(int) $terminal->id] ?? 0), 2),
            ];
        }

        if ($unassignedPosVes > 0.00001) {
            $posTerminals[] = [
                'id' => null,
                'label' => 'POS sin punto asignado',
                'amount_ves' => $unassignedPosVes,
            ];
        }

        return $posTerminals;
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
