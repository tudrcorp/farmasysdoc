<?php

namespace App\Support\Products;

use App\Models\FefoPosAlertLog;
use App\Models\InventoryStockFailure;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

final class ProductDeletion
{
    public static function deleteDependentLogs(Product $product): void
    {
        if (Schema::hasTable('fallas_existencia')) {
            InventoryStockFailure::query()->where('product_id', $product->id)->delete();
        }

        if (Schema::hasTable((new FefoPosAlertLog)->getTable())) {
            FefoPosAlertLog::query()->where('product_id', $product->id)->delete();
        }
    }

    public static function isRestrictForeignKeyViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = $exception->getMessage();

        if ($sqlState !== '23000') {
            return false;
        }

        return $driverCode === 1451
            || str_contains($message, '1451')
            || str_contains(strtolower($message), 'foreign key constraint');
    }
}
