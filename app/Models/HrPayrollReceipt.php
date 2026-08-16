<?php

namespace App\Models;

use Database\Factories\HrPayrollReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollReceipt extends Model
{
    /** @use HasFactory<HrPayrollReceiptFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'worker_name',
        'national_id',
        'month',
        'month_label',
        'year',
        'branch_name',
        'branch_address',
        'legal_salary_monthly_ves',
        'legal_salary_biweekly_ves',
        'assignments_ves',
        'deductions_ves',
        'total_ves',
        'items',
        'emailed_at',
        'whatsapped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'legal_salary_monthly_ves' => 'decimal:2',
            'legal_salary_biweekly_ves' => 'decimal:2',
            'assignments_ves' => 'decimal:2',
            'deductions_ves' => 'decimal:2',
            'total_ves' => 'decimal:2',
            'items' => 'array',
            'emailed_at' => 'datetime',
            'whatsapped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return list<array{type: string, name: string, amount_ves: float}>
     */
    public function assignmentItems(): array
    {
        return array_values(array_filter(
            $this->items ?? [],
            fn (mixed $item): bool => is_array($item) && ($item['type'] ?? '') === 'assignment',
        ));
    }

    /**
     * @return list<array{type: string, name: string, amount_ves: float}>
     */
    public function deductionItems(): array
    {
        return array_values(array_filter(
            $this->items ?? [],
            fn (mixed $item): bool => is_array($item) && ($item['type'] ?? '') === 'deduction',
        ));
    }

    public function fileName(): string
    {
        return sprintf('recibo-nomina-%04d-%02d-%d.pdf', $this->year, $this->month, $this->employee_id);
    }

    public function periodLabel(): string
    {
        return trim($this->month_label.' '.$this->year);
    }
}
