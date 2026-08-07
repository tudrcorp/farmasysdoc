<?php

namespace App\Support\Sales;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ventas internas generadas al completar un traslado entre sucursales.
 * No deben listarse ni sumarse con ventas de caja / POS.
 */
final class InternalBranchTransferSale
{
    public const PAYMENT_METHOD = 'traslado_sucursal';

    public static function is(?Sale $sale): bool
    {
        if (! $sale instanceof Sale) {
            return false;
        }

        return self::matchesPaymentMethod($sale->payment_method);
    }

    public static function matchesPaymentMethod(mixed $paymentMethod): bool
    {
        if (! is_string($paymentMethod) && ! is_numeric($paymentMethod)) {
            return false;
        }

        return strtolower(trim((string) $paymentMethod)) === self::PAYMENT_METHOD;
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public static function excludeFromQuery(Builder $query): Builder
    {
        $column = $query->qualifyColumn('payment_method');

        return $query->where(function (Builder $inner) use ($column): void {
            $inner->whereNull($column)
                ->orWhere($column, '!=', self::PAYMENT_METHOD);
        });
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public static function restrictQuery(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('payment_method'), self::PAYMENT_METHOD);
    }
}
