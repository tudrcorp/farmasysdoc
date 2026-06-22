<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSalesGoal extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'period_year',
        'period_month',
        'is_global',
        'branch_id',
        'goal_usd',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'is_global' => 'boolean',
            'goal_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        return $query
            ->where('period_year', $year)
            ->where('period_month', $month);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query
            ->where('is_global', false)
            ->where('branch_id', $branchId);
    }

    public function periodLabel(): string
    {
        $date = Carbon::createFromDate(
            (int) $this->period_year,
            (int) $this->period_month,
            1,
        )->locale('es');

        return ucfirst($date->translatedFormat('F Y'));
    }

    public function scopeLabel(): string
    {
        if ($this->is_global) {
            return 'Meta global';
        }

        if ($this->relationLoaded('branch') && filled($this->branch?->name)) {
            return (string) $this->branch->name;
        }

        if ($this->branch_id !== null) {
            return 'Sucursal #'.$this->branch_id;
        }

        return 'Sucursal';
    }

    /**
     * @return array<int, string>
     */
    public static function monthOptions(): array
    {
        $options = [];

        for ($month = 1; $month <= 12; $month++) {
            $label = ucfirst(
                Carbon::createFromDate(now()->year, $month, 1)
                    ->locale('es')
                    ->translatedFormat('F'),
            );
            $options[$month] = $label;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function yearOptions(): array
    {
        $currentYear = (int) now()->year;
        $options = [];

        for ($year = $currentYear - 1; $year <= $currentYear + 1; $year++) {
            $options[$year] = (string) $year;
        }

        return $options;
    }
}
