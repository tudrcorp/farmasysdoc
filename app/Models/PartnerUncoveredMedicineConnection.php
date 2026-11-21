<?php

namespace App\Models;

use App\Enums\UncoveredMedicineSyncStatus;
use Database\Factories\PartnerUncoveredMedicineConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Credencial y estado de la copia local de medicamentos no cubiertos de un aliado.
 *
 * El token no se muestra de nuevo y no entra en el log de auditoría.
 */
class PartnerUncoveredMedicineConnection extends Model
{
    /** @use HasFactory<PartnerUncoveredMedicineConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'token',
        'sync_status',
        'sync_run_id',
        'sync_started_at',
        'last_synced_at',
        'last_error',
        'receipt_synced_at',
        'receipt_sync_error',
        'rate_limit_limit',
        'rate_limit_remaining',
        'profile',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'sync_status' => UncoveredMedicineSyncStatus::class,
            'sync_started_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'receipt_synced_at' => 'datetime',
            'rate_limit_limit' => 'integer',
            'rate_limit_remaining' => 'integer',
            'profile' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    public function hasToken(): bool
    {
        return filled($this->token);
    }

    public static function queueCacheKey(int $partnerCompanyId): string
    {
        return 'partner-uncovered-medicines-sync:'.$partnerCompanyId;
    }

    public static function releaseSyncLock(int $partnerCompanyId, ?string $runId = null): void
    {
        $key = self::queueCacheKey($partnerCompanyId);
        if ($runId !== null && Cache::get($key) !== $runId) {
            return;
        }

        Cache::forget($key);
    }

    public static function staleRunningMinutes(): int
    {
        return max(1, (int) config('partner_uncovered_medicines.stale_running_minutes', 15));
    }

    /**
     * Hay una actualización en curso que todavía no se considera colgada.
     */
    public function isSyncActive(): bool
    {
        if ($this->sync_status !== UncoveredMedicineSyncStatus::Running) {
            return Cache::has(self::queueCacheKey((int) $this->partner_company_id))
                && ($this->sync_started_at === null || $this->sync_started_at->gte(now()->subMinutes(self::staleRunningMinutes())));
        }

        $startedAt = $this->sync_started_at;

        return $startedAt instanceof Carbon
            && $startedAt->gte(now()->subMinutes(self::staleRunningMinutes()));
    }
}
