<?php

namespace App\Services\Hr;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PayrollPeriodGenerator
{
    /**
     * Genera de forma idempotente los 24 periodos del año (día 15 y fin de mes).
     *
     * @return Collection<int, PayrollPeriod>
     */
    public function generateForYear(int $year): Collection
    {
        $created = collect();
        $periodNumber = 1;

        for ($month = 1; $month <= 12; $month++) {
            $mid = Carbon::create($year, $month, 15)->startOfDay();
            $end = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

            foreach ([$mid, $end] as $date) {
                $period = PayrollPeriod::query()->firstOrCreate(
                    [
                        'year' => $year,
                        'period_number' => $periodNumber,
                    ],
                    [
                        'period_date' => $date->toDateString(),
                        'status' => PayrollPeriodStatus::Draft,
                    ],
                );

                $created->push($period);
                $periodNumber++;
            }
        }

        return $created;
    }
}
