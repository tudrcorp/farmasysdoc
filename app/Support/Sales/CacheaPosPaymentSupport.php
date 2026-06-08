<?php

namespace App\Support\Sales;

use Filament\Schemas\Components\Utilities\Get;

final class CacheaPosPaymentSupport
{
    /**
     * @return array<string, string>
     */
    public static function complementOptions(): array
    {
        return [
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'punto_venta_ves' => 'Punto de Venta',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'pago_movil' => 'Pago Movil',
        ];
    }

    public static function complementLabel(?string $method): string
    {
        if (blank($method)) {
            return '—';
        }

        return self::complementOptions()[$method] ?? (string) $method;
    }

    public static function remainderStatusLabel(float $remainder): string
    {
        return $remainder > 0.00001 ? 'Pendiente Cachea' : 'Liquidado';
    }

    public static function remainderStatusColor(float $remainder): string
    {
        return $remainder > 0.00001 ? 'warning' : 'success';
    }

    public static function complementBadgeColor(?string $method): string
    {
        return match ($method) {
            'efectivo_usd', 'zelle' => 'success',
            'pago_movil', 'transfer_ves' => 'info',
            'punto_venta_ves' => 'primary',
            'efectivo_ves' => 'warning',
            default => 'gray',
        };
    }

    public static function isCacheaPayment(string $paymentMethod, mixed $payWithCachea = false): bool
    {
        return $paymentMethod === PosPaymentMethodOptions::CACHEA
            || filter_var($payWithCachea, FILTER_VALIDATE_BOOLEAN);
    }

    public static function paidAmountFromGet(Get $get): float
    {
        return self::normalizePaidAmount($get('cachea_paid_amount'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function paidAmountFromData(array $data): float
    {
        return self::normalizePaidAmount($data['cachea_paid_amount'] ?? 0);
    }

    public static function remainder(float $documentTotalUsd, float $cacheaPaidUsd): float
    {
        return round(max(0.0, $documentTotalUsd - self::normalizePaidAmount($cacheaPaidUsd)), 2);
    }

    public static function complementMethodFromGet(Get $get): string
    {
        return self::normalizeComplementMethod((string) ($get('cachea_complement_payment_method') ?? 'efectivo_usd'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function complementMethodFromData(array $data): string
    {
        return self::normalizeComplementMethod((string) ($data['cachea_complement_payment_method'] ?? 'efectivo_usd'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     cachea_paid_amount: float,
     *     remainder: float,
     *     complement_payment_method: string,
     *     payment_usd: float,
     *     payment_ves: float,
     * }
     */
    public static function breakdown(float $documentTotalUsd, array $data, float $vesUsdRate): array
    {
        $cacheaPaid = min(self::paidAmountFromData($data), round($documentTotalUsd, 2));
        $remainder = self::remainder($documentTotalUsd, $cacheaPaid);
        $complement = self::complementMethodFromData($data);

        [$complementUsd, $complementVes] = $remainder > 0.00001
            ? self::resolveComplementAmounts($remainder, $complement, $vesUsdRate)
            : [0.0, 0.0];

        return [
            'cachea_paid_amount' => $cacheaPaid,
            'remainder' => $remainder,
            'complement_payment_method' => $complement,
            'payment_usd' => round($cacheaPaid + $complementUsd, 2),
            'payment_ves' => $complementVes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function usesPagoMovilComplement(string $paymentMethod, array $data, float $documentTotalUsd): bool
    {
        if ($paymentMethod !== PosPaymentMethodOptions::CACHEA) {
            return false;
        }

        $remainder = self::remainder($documentTotalUsd, self::paidAmountFromData($data));

        return self::complementMethodFromData($data) === 'pago_movil'
            && $remainder > 0.00001;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function usesPointOfSaleComplement(string $paymentMethod, array $data, float $documentTotalUsd): bool
    {
        if ($paymentMethod !== PosPaymentMethodOptions::CACHEA) {
            return false;
        }

        $remainder = self::remainder($documentTotalUsd, self::paidAmountFromData($data));

        return self::complementMethodFromData($data) === 'punto_venta_ves'
            && $remainder > 0.00001;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function complementRequiresReference(string $paymentMethod, array $data, float $documentTotalUsd): bool
    {
        if ($paymentMethod !== PosPaymentMethodOptions::CACHEA) {
            return false;
        }

        $remainder = self::remainder($documentTotalUsd, self::paidAmountFromData($data));
        if ($remainder <= 0.00001) {
            return false;
        }

        return in_array(self::complementMethodFromData($data), ['transfer_ves', 'zelle'], true);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function resolveComplementAmounts(float $amountUsd, string $complementMethod, float $vesUsdRate): array
    {
        $rate = max(0.0, $vesUsdRate);

        return match ($complementMethod) {
            'transfer_ves', 'pago_movil', 'efectivo_ves', 'punto_venta_ves' => [0.0, round($amountUsd * $rate, 2)],
            default => [round($amountUsd, 2), 0.0],
        };
    }

    private static function normalizePaidAmount(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(max(0.0, (float) $value), 2);
    }

    private static function normalizeComplementMethod(string $method): string
    {
        return array_key_exists($method, self::complementOptions())
            ? $method
            : 'efectivo_usd';
    }
}
