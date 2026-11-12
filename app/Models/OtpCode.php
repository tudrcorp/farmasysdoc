<?php

namespace App\Models;

use App\Enums\OtpCodePurpose;
use App\Enums\OtpCodeStatus;
use Database\Factories\OtpCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clave OTP generada por el sistema. Gerentes y administradores la consultan en «Claves OTP»;
 * al usarse queda anulada con quién la usó y en qué parte del sistema.
 *
 * @property-read OtpCodeStatus $effective_status
 */
class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use HasFactory;

    public const TTL_SECONDS = 600;

    public const MAX_FAILED_ATTEMPTS = 5;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'status',
        'code',
        'fingerprint',
        'requested_by_user_id',
        'branch_id',
        'summary',
        'details',
        'failed_attempts',
        'expires_at',
        'used_by_user_id',
        'used_at',
        'used_in',
        'used_url',
        'self_applied',
        'self_applied_at',
        'self_applied_ip',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'code',
        'fingerprint',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpCodePurpose::class,
            'status' => OtpCodeStatus::class,
            'code' => 'encrypted',
            'details' => 'array',
            'failed_attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'self_applied' => 'boolean',
            'self_applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function getEffectiveStatusAttribute(): OtpCodeStatus
    {
        if ($this->status === OtpCodeStatus::Pending && $this->isExpired()) {
            return OtpCodeStatus::Expired;
        }

        return $this->status;
    }

    /**
     * @param  Builder<OtpCode>  $query
     * @return Builder<OtpCode>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', OtpCodeStatus::Pending->value)
            ->where('expires_at', '>', now());
    }

    /**
     * Filtra por estado efectivo («Vencida» = pendiente con `expires_at` pasado).
     *
     * @param  Builder<OtpCode>  $query
     * @return Builder<OtpCode>
     */
    public function scopeWithEffectiveStatus(Builder $query, OtpCodeStatus $status): Builder
    {
        return match ($status) {
            OtpCodeStatus::Pending => $query->active(),
            OtpCodeStatus::Expired => $query->where(function (Builder $expired): void {
                $expired->where('status', OtpCodeStatus::Expired->value)
                    ->orWhere(function (Builder $pending): void {
                        $pending->where('status', OtpCodeStatus::Pending->value)
                            ->where('expires_at', '<=', now());
                    });
            }),
            default => $query->where('status', $status->value),
        };
    }

    /**
     * Claves que el usuario puede ver: administradores todas y gerentes las de sus sucursales,
     * nunca las que el propio usuario solicitó.
     *
     * @param  Builder<OtpCode>  $query
     * @return Builder<OtpCode>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where(function (Builder $notOwn) use ($user): void {
            $notOwn->whereNull('requested_by_user_id')
                ->orWhere('requested_by_user_id', '!=', $user->getKey());
        });

        if ($user->isAdministrator()) {
            return $query;
        }

        if (! $user->isManager()) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = $user->restrictedBranchIdsForQueries();

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->qualifyColumn('branch_id'), $branchIds);
    }
}
