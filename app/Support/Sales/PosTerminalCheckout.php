<?php

namespace App\Support\Sales;

use App\Models\PosTerminal;
use Illuminate\Support\Facades\Auth;

final class PosTerminalCheckout
{
    /**
     * @return array<int, string>
     */
    public static function optionsForBranch(?int $branchId): array
    {
        if ($branchId === null || $branchId <= 0) {
            return [];
        }

        return PosTerminal::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('bank_code')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(static fn (PosTerminal $terminal): array => [
                (int) $terminal->id => $terminal->displayLabel(),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForAuthenticatedCashier(): array
    {
        $branchId = Auth::user()?->branch_id;

        return self::optionsForBranch(filled($branchId) ? (int) $branchId : null);
    }

    public static function findActiveForBranch(int $branchId, mixed $terminalId): ?PosTerminal
    {
        $id = (int) $terminalId;

        if ($id <= 0 || $branchId <= 0) {
            return null;
        }

        return PosTerminal::query()
            ->whereKey($id)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();
    }
}
