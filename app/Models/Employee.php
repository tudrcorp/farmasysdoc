<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'national_id',
        'phone',
        'email',
        'address',
        'monthly_salary_usd',
        'branch_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_salary_usd' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<HrAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(HrAssignment::class);
    }

    /**
     * @return HasMany<HrDeduction, $this>
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(HrDeduction::class);
    }

    /**
     * @return HasMany<HrLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(HrLoan::class);
    }

    /**
     * @return HasMany<PayrollLine, $this>
     */
    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
