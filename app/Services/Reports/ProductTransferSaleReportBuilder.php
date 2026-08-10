<?php

namespace App\Services\Reports;

use App\Models\ProductTransfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ProductTransferSaleReportBuilder
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
            ->with(['fromBranch', 'toBranch', 'items.product', 'sale', 'client', 'deliveryUser'])
            ->where('transfer_type', 'sale_transfer')
            ->whereBetween('created_at', [$from, $until])
            ->orderBy('created_at');

        $this->applyUserScope($query, $user);

        /** @var Collection<int, ProductTransfer> $transfers */
        $transfers = $query->get();

        $totalLines = 0;
        $totalCostSum = 0.0;
        foreach ($transfers as $transfer) {
            $totalLines += $transfer->items->count();
            if ($transfer->total_transfer_cost !== null) {
                $totalCostSum += (float) $transfer->total_transfer_cost;
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

    /**
     * @param  Builder<ProductTransfer>  $query
     */
    private function applyUserScope(Builder $query, ?User $user): void
    {
        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return;
        }

        $branchIds = $user->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($branchIds): void {
            $q->whereIn('to_branch_id', $branchIds)
                ->orWhereIn('from_branch_id', $branchIds);
        });
    }
}
