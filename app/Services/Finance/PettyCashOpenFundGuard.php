<?php

namespace App\Services\Finance;

use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use RuntimeException;

final class PettyCashOpenFundGuard
{
    public function assertCanRequestForBranch(int $branchId, ?int $ignoreFundId = null): void
    {
        $query = PettyCashFund::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                PettyCashFundStatus::PendingApproval->value,
                PettyCashFundStatus::Approved->value,
            ]);

        if ($ignoreFundId !== null) {
            $query->whereKeyNot($ignoreFundId);
        }

        if ($query->exists()) {
            throw new RuntimeException(
                'Esta sucursal ya tiene una caja chica pendiente o con saldo. Agótela o espere la aprobación antes de solicitar otra.',
            );
        }
    }
}
