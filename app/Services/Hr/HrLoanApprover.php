<?php

namespace App\Services\Hr;

use App\Enums\HrLoanStatus;
use App\Models\HrLoan;
use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

final class HrLoanApprover
{
    public function approve(HrLoan $loan, User $admin): HrLoan
    {
        if (! $admin->isAdministrator()) {
            throw new RuntimeException('Solo un administrador puede aprobar préstamos.');
        }

        if ($loan->status !== HrLoanStatus::PendingApproval) {
            throw new InvalidArgumentException('El préstamo no está pendiente de aprobación.');
        }

        $loan->forceFill([
            'status' => HrLoanStatus::Active,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'remaining_usd' => $loan->remaining_usd > 0 ? $loan->remaining_usd : $loan->amount_usd,
        ])->save();

        return $loan->refresh();
    }

    public function reject(HrLoan $loan, User $admin): HrLoan
    {
        if (! $admin->isAdministrator()) {
            throw new RuntimeException('Solo un administrador puede rechazar préstamos.');
        }

        if ($loan->status !== HrLoanStatus::PendingApproval) {
            throw new InvalidArgumentException('El préstamo no está pendiente de aprobación.');
        }

        $loan->forceFill([
            'status' => HrLoanStatus::Rejected,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ])->save();

        return $loan->refresh();
    }
}
