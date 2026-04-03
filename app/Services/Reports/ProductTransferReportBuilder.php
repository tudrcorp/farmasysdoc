<?php

namespace App\Services\Reports;

use App\Models\ProductTransfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ProductTransferReportBuilder
{
    /**
     * @return array{
     *     from: Carbon,
     *     until: Carbon,
     *     transfers: Collection<int, ProductTransfer>,
     *     generated_at: string,
     *     generated_by: string,
     *     total_lines: int,
     *     total_cost_sum: float,
     * }
     */
    public function build(Carbon $from, Carbon $until, ?User $user): array
    {
        $query = ProductTransfer::query()
            ->with(['fromBranch', 'toBranch', 'items.product'])
            ->whereBetween('created_at', [$from, $until])
            ->orderBy('created_at');

        if ($user instanceof User && ! $user->isAdministrator()) {
            if (! filled($user->branch_id)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('to_branch_id', (int) $user->branch_id);
            }
        }

        /** @var Collection<int, ProductTransfer> $transfers */
        $transfers = $query->get();

        $totalLines = 0;
        $totalCostSum = 0.0;
        foreach ($transfers as $t) {
            $totalLines += $t->items->count();
            if ($t->total_transfer_cost !== null) {
                $totalCostSum += (float) $t->total_transfer_cost;
            }
        }

        $actor = $user !== null
            ? (filled($user->email) ? (string) $user->email : (string) ($user->name ?? 'usuario'))
            : 'sistema';

        return [
            'from' => $from,
            'until' => $until,
            'transfers' => $transfers,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => $actor,
            'total_lines' => $totalLines,
            'total_cost_sum' => round($totalCostSum, 2),
        ];
    }
}
