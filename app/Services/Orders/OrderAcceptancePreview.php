<?php

namespace App\Services\Orders;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Finance\BcvRate;
use App\Support\Inventory\LasDeliciasBranch;
use App\Support\Orders\OrderTotalsCalculator;

final class OrderAcceptancePreview
{
    /**
     * @return array{
     *     branch_name: ?string,
     *     branch_ready: bool,
     *     rows: list<array{
     *         order_item_id: int,
     *         name: string,
     *         barcode: string,
     *         sku: string,
     *         quantity: float,
     *         on_hand: float,
     *         unit_price_usd: float,
     *         line_total_usd: float,
     *         can_accept: bool,
     *         block_reason: null
     *     }>
     * }
     */
    public function build(Order $order): array
    {
        $order->loadMissing('items.product');

        $branch = LasDeliciasBranch::resolve();
        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $inventories = $branch === null || $productIds->isEmpty()
            ? collect()
            : Inventory::query()
                ->where('branch_id', $branch->getKey())
                ->whereIn('product_id', $productIds)
                ->get()
                ->keyBy('product_id');

        $rows = [];

        foreach ($order->items as $item) {
            if (! $item instanceof OrderItem) {
                continue;
            }

            $quantity = round((float) $item->quantity, 3);
            $inventory = $item->product_id === null ? null : $inventories->get($item->product_id);
            $priced = OrderTotalsCalculator::acceptanceSaleAmounts(
                $item,
                $branch?->getKey(),
                $inventory instanceof Inventory ? $inventory : null,
            );

            $onHand = $inventory instanceof Inventory
                ? round((float) $inventory->quantity, 3)
                : 0.0;

            $rows[] = [
                'order_item_id' => (int) $item->getKey(),
                'name' => filled($item->product_name_snapshot)
                    ? (string) $item->product_name_snapshot
                    : (string) ($item->product?->name ?? 'Producto'),
                'barcode' => trim((string) ($item->product?->barcode ?? '')),
                'sku' => filled($item->sku_snapshot) ? (string) $item->sku_snapshot : (string) ($item->product?->sku ?? ''),
                'quantity' => $quantity,
                'on_hand' => $onHand,
                'unit_price_usd' => $priced['unit_price_usd'],
                'line_total_usd' => $priced['line_total_usd'],
                'can_accept' => true,
                'block_reason' => null,
            ];
        }

        return [
            'branch_name' => $branch?->name,
            'branch_ready' => $branch !== null,
            'rows' => $rows,
        ];
    }

    public function lineTotalVes(float $lineUsd, ?float $rate): float
    {
        $normalized = BcvRate::truncateOrNull($rate);

        if ($normalized === null) {
            return 0.0;
        }

        return round($lineUsd * $normalized, 2);
    }
}
