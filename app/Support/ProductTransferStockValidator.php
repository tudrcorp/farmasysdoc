<?php

namespace App\Support;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductTransferStockValidator
{
    /**
     * @param  array<string, mixed>  $itemsState  Estado del Repeater (claves record-* o UUID).
     */
    public static function assertSufficientStockAtBranch(int $fromBranchId, array $itemsState): void
    {
        if ($fromBranchId <= 0) {
            return;
        }

        $totalsByProduct = self::aggregateQuantitiesByProductId($itemsState);
        if ($totalsByProduct === []) {
            return;
        }

        $productIds = array_keys($totalsByProduct);
        $inventories = Inventory::query()
            ->where('branch_id', $fromBranchId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $errors = [];

        foreach ($totalsByProduct as $productId => $neededQty) {
            $inv = $inventories->get($productId);
            $available = $inv !== null
                ? (float) $inv->quantity
                : 0.0;

            if ($inv !== null && $inv->allow_negative_stock) {
                continue;
            }

            if ($available + 0.0001 < $neededQty) {
                $name = Product::query()->whereKey($productId)->value('name') ?? 'Producto #'.$productId;
                $errors[] = $name.': se requieren '.self::fmtQty($neededQty).' u.; disponible '.self::fmtQty(max(0.0, $available)).' u.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'data.items' => implode(' ', $errors),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $itemsState
     * @return array<int, float>
     */
    public static function aggregateQuantitiesByProductId(array $itemsState): array
    {
        $totals = [];

        foreach ($itemsState as $row) {
            if (! is_array($row)) {
                continue;
            }

            $pid = $row['product_id'] ?? null;
            if (! filled($pid)) {
                continue;
            }

            $qty = (float) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $id = (int) $pid;
            $totals[$id] = ($totals[$id] ?? 0.0) + $qty;
        }

        return $totals;
    }

    /**
     * Valida existencias contra la BD para un traslado ya guardado (ítems en tabla).
     */
    public static function assertPersistedItemsSufficientStock(int $fromBranchId, int $transferId): void
    {
        $rows = DB::table('product_transfer_items')
            ->where('product_transfer_id', $transferId)
            ->select(['product_id', 'quantity'])
            ->get();

        $itemsState = [];
        foreach ($rows as $i => $row) {
            $itemsState['k'.$i] = [
                'product_id' => $row->product_id,
                'quantity' => $row->quantity,
            ];
        }

        self::assertSufficientStockAtBranch($fromBranchId, $itemsState);
    }

    private static function fmtQty(float $q): string
    {
        $s = rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}
