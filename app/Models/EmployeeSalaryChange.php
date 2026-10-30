<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de sueldos: el sistema solo guardaba el sueldo vigente y la garantía trimestral
 * necesita el de cada trimestre.
 */
class EmployeeSalaryChange extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'monthly_salary_usd',
        'legal_salary_ves',
        'effective_on',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_salary_usd' => 'decimal:2',
            'legal_salary_ves' => 'decimal:2',
            'effective_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
