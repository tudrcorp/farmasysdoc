<?php

namespace App\Models;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Enums\EmploymentCertificateSalaryBasis;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitud hecha por el empleado desde el portal: constancia, vacaciones, permiso o reposo.
 */
class EmployeeRequest extends Model
{
    /** @use HasFactory<EmployeeRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'branch_id',
        'type',
        'status',
        'starts_on',
        'ends_on',
        'days_count',
        'salary_basis',
        'is_early',
        'entitled_days',
        'entitlement_starts_on',
        'reason',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'cancelled_at',
        'employee_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmployeeRequestType::class,
            'status' => EmployeeRequestStatus::class,
            'salary_basis' => EmploymentCertificateSalaryBasis::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'entitlement_starts_on' => 'date',
            'days_count' => 'integer',
            'entitled_days' => 'integer',
            'is_early' => 'boolean',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'employee_seen_at' => 'datetime',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<EmployeeRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmployeeRequestAttachment::class);
    }

    /**
     * Decisiones del administrador que el empleado aún no ha visto en el portal.
     *
     * @param  Builder<EmployeeRequest>  $query
     */
    public function scopeUnseenDecisions(Builder $query): void
    {
        $query->whereNotNull('reviewed_at')
            ->whereNull('employee_seen_at')
            ->whereIn('status', [EmployeeRequestStatus::Approved, EmployeeRequestStatus::Rejected]);
    }

    /**
     * Solicitudes vigentes cuyo rango de fechas se cruza con el indicado.
     *
     * @param  Builder<EmployeeRequest>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $startsOn, CarbonInterface $endsOn): void
    {
        $query->whereIn('status', EmployeeRequestStatus::active())
            ->whereNotNull('starts_on')
            ->whereDate('starts_on', '<=', $endsOn->toDateString())
            ->whereDate('ends_on', '>=', $startsOn->toDateString());
    }

    public function isPending(): bool
    {
        return $this->status === EmployeeRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === EmployeeRequestStatus::Approved;
    }

    public function canDownloadCertificate(): bool
    {
        return $this->type === EmployeeRequestType::EmploymentCertificate && $this->isApproved();
    }

    public function periodLabel(): ?string
    {
        if ($this->starts_on === null || $this->ends_on === null) {
            return null;
        }

        if ($this->starts_on->isSameDay($this->ends_on)) {
            return $this->starts_on->format('d/m/Y');
        }

        return $this->starts_on->format('d/m/Y').' al '.$this->ends_on->format('d/m/Y');
    }

    public function summaryLabel(): string
    {
        if ($this->type === EmployeeRequestType::EmploymentCertificate) {
            return $this->salary_basis?->label() ?? 'Constancia de trabajo';
        }

        $days = $this->days_count !== null
            ? ' · '.$this->days_count.' '.($this->days_count === 1 ? 'día' : 'días')
            : '';

        return ($this->periodLabel() ?? '—').$days;
    }
}
