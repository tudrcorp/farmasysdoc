<?php

namespace App\Support\Purchases;

use App\Enums\MedicationQuoteAdjustment;

final class MedicationQuoteTotals
{
    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{subtotal_usd: float, total_usd: float, factor: float}
     */
    public static function calculate(array $lines, MedicationQuoteAdjustment|string|null $kind, float $percent): array
    {
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $quantity = max(0, (float) ($line['quantity'] ?? 0));
            $unitPrice = max(0, (float) ($line['unit_price_usd'] ?? 0));
            $subtotal += round($quantity * $unitPrice, 2);
        }

        $subtotal = round($subtotal, 2);
        $adjustment = $kind instanceof MedicationQuoteAdjustment
            ? $kind
            : MedicationQuoteAdjustment::tryFrom((string) $kind) ?? MedicationQuoteAdjustment::None;
        $percent = max(0, $percent);

        $factor = match ($adjustment) {
            MedicationQuoteAdjustment::Discount => max(0, 1 - ($percent / 100)),
            MedicationQuoteAdjustment::Increase => 1 + ($percent / 100),
            MedicationQuoteAdjustment::None => 1.0,
        };

        return [
            'subtotal_usd' => $subtotal,
            'total_usd' => round($subtotal * $factor, 2),
            'factor' => $factor,
        ];
    }
}
