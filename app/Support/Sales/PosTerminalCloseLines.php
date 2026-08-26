<?php

namespace App\Support\Sales;

use App\Models\PosTerminal;

final class PosTerminalCloseLines
{
    /**
     * @param  array<int, float>  $totalsByTerminalId
     * @return list<array{id: int|null, label: string, amount_ves: float}>
     */
    public function build(?int $branchId, array $totalsByTerminalId, float $unassignedPosVes): array
    {
        $terminals = PosTerminal::query()
            ->when(
                $branchId !== null && $branchId > 0,
                fn ($query) => $query->where('branch_id', $branchId),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
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
}
