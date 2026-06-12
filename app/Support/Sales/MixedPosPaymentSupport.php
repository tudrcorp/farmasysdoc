<?php

namespace App\Support\Sales;

/**
 * Pago múltiple en caja: parte en USD + resto en un método VES, o división entre dos métodos VES.
 */
final class MixedPosPaymentSupport
{
    public const MODE_USD = 'usd';

    public const MODE_VES = 'ves';

    /**
     * @return array<string, string>
     */
    public static function vesMethodOptions(): array
    {
        return [
            'punto_venta_ves' => 'Punto de Venta',
            'transfer_ves' => 'Transferencia VES',
            'pago_movil' => 'Pago Movil',
            'efectivo_ves' => 'Efectivo VES',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveMode(array $data): string
    {
        if (filter_var($data['mixed_use_ves_portion'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return self::MODE_VES;
        }

        return self::MODE_USD;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isUsdMode(array $data): bool
    {
        return self::resolveMode($data) === self::MODE_USD;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isVesMode(array $data): bool
    {
        return self::resolveMode($data) === self::MODE_VES;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     mode: string,
     *     payment_usd: float,
     *     payment_ves: float,
     *     usd_mode_ves_method?: string,
     *     ves_split?: array{
     *         method_1: string,
     *         amount_1: float,
     *         method_2: string,
     *         amount_2: float,
     *     },
     * }
     */
    public static function breakdown(float $documentTotalUsd, float $vesUsdRate, array $data): array
    {
        $rate = max(0.0, $vesUsdRate);
        $totalVes = round(max(0.0, $documentTotalUsd) * $rate, 2);

        if (self::isVesMode($data)) {
            $amount1 = round(max(0.0, (float) ($data['mixed_ves_split_amount_1'] ?? 0)), 2);
            $amount2 = round(max(0.0, $totalVes - $amount1), 2);

            return [
                'mode' => self::MODE_VES,
                'payment_usd' => 0.0,
                'payment_ves' => $totalVes,
                'ves_split' => [
                    'method_1' => self::normalizeVesMethod($data['mixed_ves_split_method_1'] ?? null),
                    'amount_1' => $amount1,
                    'method_2' => self::normalizeVesMethod($data['mixed_ves_split_method_2'] ?? null),
                    'amount_2' => $amount2,
                ],
            ];
        }

        $mixedUsdPaid = round(max(0.0, min($documentTotalUsd, (float) ($data['mixed_usd_paid'] ?? 0))), 2);
        $paymentVes = round(max(0.0, $documentTotalUsd - $mixedUsdPaid) * $rate, 2);

        return [
            'mode' => self::MODE_USD,
            'payment_usd' => $mixedUsdPaid,
            'payment_ves' => $paymentVes,
            'usd_mode_ves_method' => self::normalizeVesMethod($data['mixed_ves_payment_method'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function selectedUsdModeVesMethod(array $data): string
    {
        return self::normalizeVesMethod($data['mixed_ves_payment_method'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function vesPortionUsesPagoMovil(array $data, float $documentTotalUsd, float $vesUsdRate): bool
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);

        if ($breakdown['mode'] === self::MODE_USD) {
            return ($breakdown['usd_mode_ves_method'] ?? '') === 'pago_movil'
                && ($breakdown['payment_ves'] ?? 0.0) > 0.00001;
        }

        $split = $breakdown['ves_split'] ?? null;
        if (! is_array($split)) {
            return false;
        }

        return ($split['method_1'] === 'pago_movil' && ($split['amount_1'] ?? 0.0) > 0.00001)
            || ($split['method_2'] === 'pago_movil' && ($split['amount_2'] ?? 0.0) > 0.00001);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function pagoMovilVesAmount(array $data, float $documentTotalUsd, float $vesUsdRate): float
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);

        if ($breakdown['mode'] === self::MODE_USD) {
            return ($breakdown['usd_mode_ves_method'] ?? '') === 'pago_movil'
                ? (float) ($breakdown['payment_ves'] ?? 0.0)
                : 0.0;
        }

        $split = $breakdown['ves_split'] ?? [];
        $total = 0.0;
        if (($split['method_1'] ?? '') === 'pago_movil') {
            $total += (float) ($split['amount_1'] ?? 0.0);
        }
        if (($split['method_2'] ?? '') === 'pago_movil') {
            $total += (float) ($split['amount_2'] ?? 0.0);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function vesPortionUsesPointOfSale(array $data, float $documentTotalUsd, float $vesUsdRate): bool
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);

        if ($breakdown['mode'] === self::MODE_USD) {
            return ($breakdown['usd_mode_ves_method'] ?? '') === 'punto_venta_ves'
                && ($breakdown['payment_ves'] ?? 0.0) > 0.00001;
        }

        $split = $breakdown['ves_split'] ?? null;
        if (! is_array($split)) {
            return false;
        }

        return ($split['method_1'] === 'punto_venta_ves' && ($split['amount_1'] ?? 0.0) > 0.00001)
            || ($split['method_2'] === 'punto_venta_ves' && ($split['amount_2'] ?? 0.0) > 0.00001);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function requiresPaymentReference(array $data, float $documentTotalUsd, float $vesUsdRate): bool
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);

        if ($breakdown['mode'] === self::MODE_USD) {
            return ($breakdown['usd_mode_ves_method'] ?? '') === 'transfer_ves'
                && ($breakdown['payment_ves'] ?? 0.0) > 0.00001;
        }

        $split = $breakdown['ves_split'] ?? null;
        if (! is_array($split)) {
            return false;
        }

        return ($split['method_1'] === 'transfer_ves' && ($split['amount_1'] ?? 0.0) > 0.00001)
            || ($split['method_2'] === 'transfer_ves' && ($split['amount_2'] ?? 0.0) > 0.00001);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{slot: string, method: string, amount_due: float, cash_received: float}>
     */
    public static function efectivoVesCashLines(array $data, float $documentTotalUsd, float $vesUsdRate): array
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);
        $lines = [];

        if ($breakdown['mode'] === self::MODE_USD) {
            $method = (string) ($breakdown['usd_mode_ves_method'] ?? '');
            $due = (float) ($breakdown['payment_ves'] ?? 0.0);
            if ($method === 'efectivo_ves' && $due > 0.00001) {
                $lines[] = [
                    'slot' => 'usd_mode',
                    'method' => $method,
                    'amount_due' => $due,
                    'cash_received' => round((float) ($data['mixed_ves_cash_received'] ?? 0), 2),
                ];
            }

            return $lines;
        }

        $split = $breakdown['ves_split'] ?? null;
        if (! is_array($split)) {
            return $lines;
        }

        if (($split['method_1'] ?? '') === 'efectivo_ves' && ($split['amount_1'] ?? 0.0) > 0.00001) {
            $lines[] = [
                'slot' => 'ves_split_1',
                'method' => 'efectivo_ves',
                'amount_due' => (float) $split['amount_1'],
                'cash_received' => round((float) ($data['mixed_ves_split_cash_received_1'] ?? 0), 2),
            ];
        }

        if (($split['method_2'] ?? '') === 'efectivo_ves' && ($split['amount_2'] ?? 0.0) > 0.00001) {
            $lines[] = [
                'slot' => 'ves_split_2',
                'method' => 'efectivo_ves',
                'amount_due' => (float) $split['amount_2'],
                'cash_received' => round((float) ($data['mixed_ves_split_cash_received_2'] ?? 0), 2),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function requiresVesConversion(float $documentTotalUsd, array $data): bool
    {
        if (self::isVesMode($data)) {
            return $documentTotalUsd > 0.00001;
        }

        $mixedUsdPaid = max(0.0, min($documentTotalUsd, (float) ($data['mixed_usd_paid'] ?? 0)));

        return max(0.0, $documentTotalUsd - $mixedUsdPaid) > 0.00001;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, message: ?string}
     */
    public static function validateBeforeRegister(array $data, float $documentTotalUsd, float $vesUsdRate): array
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);

        if ($breakdown['mode'] === self::MODE_USD) {
            $usdPaid = (float) ($breakdown['payment_usd'] ?? 0.0);
            if ($usdPaid <= 0.00001) {
                return ['valid' => false, 'message' => 'Indique el monto pagado en US$ para el pago múltiple.'];
            }

            if ($usdPaid + 0.02 >= $documentTotalUsd) {
                return ['valid' => false, 'message' => 'El monto en US$ debe ser menor al total para combinar con un pago en bolívares.'];
            }
        }

        if ($breakdown['mode'] === self::MODE_VES) {
            $split = $breakdown['ves_split'] ?? null;
            if (! is_array($split)) {
                return ['valid' => false, 'message' => 'Configure las dos formas de pago en bolívares.'];
            }

            $amount1 = (float) ($split['amount_1'] ?? 0.0);
            $amount2 = (float) ($split['amount_2'] ?? 0.0);
            $totalVes = (float) ($breakdown['payment_ves'] ?? 0.0);

            if ($amount1 <= 0.00001) {
                return ['valid' => false, 'message' => 'Indique el monto en bolívares del primer método de pago.'];
            }

            if ($amount2 <= 0.00001) {
                return ['valid' => false, 'message' => 'El primer monto en bolívares debe ser menor al total para combinar con un segundo método.'];
            }

            if (abs(($amount1 + $amount2) - $totalVes) > 0.05) {
                return ['valid' => false, 'message' => 'La suma de los montos en bolívares debe coincidir con el total de la venta en Bs.'];
            }

            if (($split['method_1'] ?? '') === ($split['method_2'] ?? '')) {
                return ['valid' => false, 'message' => 'Seleccione dos métodos de pago en bolívares distintos.'];
            }
        }

        foreach (self::efectivoVesCashLines($data, $documentTotalUsd, $vesUsdRate) as $line) {
            if ($line['amount_due'] > $line['cash_received'] + 0.02) {
                return [
                    'valid' => false,
                    'message' => 'Los bolívares recibidos en efectivo deben cubrir al menos '
                        .number_format($line['amount_due'], 2, '.', ',').' Bs.',
                ];
            }
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function buildSaleNotesSuffix(array $data, float $documentTotalUsd, float $vesUsdRate): ?string
    {
        $breakdown = self::breakdown($documentTotalUsd, $vesUsdRate, $data);
        if ($breakdown['mode'] !== self::MODE_VES || ! is_array($breakdown['ves_split'] ?? null)) {
            return null;
        }

        $split = $breakdown['ves_split'];
        $label1 = self::vesMethodOptions()[$split['method_1']] ?? $split['method_1'];
        $label2 = self::vesMethodOptions()[$split['method_2']] ?? $split['method_2'];

        return 'Pago múltiple VES: '
            .number_format((float) $split['amount_1'], 2, '.', ',').' Bs. ('.$label1.') + '
            .number_format((float) $split['amount_2'], 2, '.', ',').' Bs. ('.$label2.')';
    }

    private static function normalizeVesMethod(mixed $method): string
    {
        $key = is_string($method) ? strtolower(trim($method)) : '';

        return array_key_exists($key, self::vesMethodOptions()) ? $key : 'punto_venta_ves';
    }
}
