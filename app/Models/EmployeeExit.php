<?php

namespace App\Models;

use App\Enums\EmployeeExitChecklistItem;
use App\Enums\EmployeeExitReason;
use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeSettlementStatus;
use Database\Factories\EmployeeExitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Salida de un empleado de la empresa.
 */
class EmployeeExit extends Model
{
    /** @use HasFactory<EmployeeExitFactory> */
    use HasFactory;

    public const string DOCUMENT_DISK = 'local';

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'reason',
        'last_work_day',
        'status',
        'checklist',
        'notes',
        'document_path',
        'document_original_name',
        'cancel_reason',
        'created_by',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => EmployeeExitReason::class,
            'status' => EmployeeExitStatus::class,
            'last_work_day' => 'date',
            'checklist' => 'array',
            'closed_at' => 'datetime',
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
     * @return HasMany<EmployeeSettlement, $this>
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(EmployeeSettlement::class);
    }

    public function activeSettlement(): ?EmployeeSettlement
    {
        return $this->settlements()
            ->where('status', '!=', EmployeeSettlementStatus::Cancelled)
            ->latest('id')
            ->first();
    }

    public function isOpen(): bool
    {
        return $this->status === EmployeeExitStatus::Open;
    }

    /**
     * @return list<string>
     */
    public function pendingChecklistLabels(): array
    {
        $done = $this->checklist ?? [];
        $pending = [];

        foreach (EmployeeExitChecklistItem::cases() as $item) {
            if (! in_array($item->value, $done, true)) {
                $pending[] = $item->label();
            }
        }

        return $pending;
    }
}
