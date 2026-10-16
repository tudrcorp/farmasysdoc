<?php

namespace App\Services\Fiscal;

use App\Enums\SalesBookStatus;
use App\Models\SalesBook;
use App\Support\Fiscal\SalesBookPeriod;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

/**
 * Una quincena con Libro de Ventas cerrado no admite cambios en los datos que la componen.
 */
final class SalesBookPeriodLock
{
    public function closedBook(int $branchId, SalesBookPeriod $period): ?SalesBook
    {
        return SalesBook::query()
            ->where('branch_id', $branchId)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('half', $period->half)
            ->where('status', SalesBookStatus::Closed)
            ->latest('version')
            ->first();
    }

    public function isClosed(int $branchId, SalesBookPeriod $period): bool
    {
        return $this->closedBook($branchId, $period) instanceof SalesBook;
    }

    public function assertOpen(int $branchId, DateTimeInterface|string|null $date): void
    {
        if ($branchId <= 0 || $date === null || $date === '') {
            return;
        }

        $period = SalesBookPeriod::containing($date);

        if ($this->isClosed($branchId, $period)) {
            throw ValidationException::withMessages([
                'received_on' => 'El Libro de Ventas de la '.$period->label().' ya está cerrado para esta sucursal. Reábralo antes de modificar sus datos.',
            ]);
        }
    }
}
