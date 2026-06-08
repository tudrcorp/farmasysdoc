<?php

namespace App\Services\Inventory;

use App\Models\InventoryStockFailure;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Registra fallas de existencia detectadas en la caja registradora.
 */
final class PosInventoryStockFailureRegistrar
{
    public static function register(int $branchId, Product $product, User $user, ?float $quantity = null): void
    {
        if ($branchId <= 0 || ! Schema::hasTable('fallas_existencia')) {
            return;
        }

        InventoryStockFailure::query()->create([
            'branch_id' => $branchId,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'product_code' => self::resolveProductCode($product),
            'product_name' => (string) $product->name,
            'quantity' => round(max(0.0, $quantity ?? 0.0), 3),
        ]);
    }

    public static function resolveProductCode(Product $product): string
    {
        if (filled($product->barcode)) {
            return trim((string) $product->barcode);
        }

        if (filled($product->sku)) {
            return trim((string) $product->sku);
        }

        if (filled($product->slug)) {
            return trim((string) $product->slug);
        }

        return 'ID-'.$product->id;
    }
}
