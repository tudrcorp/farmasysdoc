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

    /**
     * Líneas seguras del perfil del aliado en Tu Dr. Group.
     *
     * @return list<array{label: string, value: string}>
     */
    public function profileLines(): array
    {
        $profile = $this->profile;
        if (! is_array($profile)) {
            return [];
        }

        $source = isset($profile['data']) && is_array($profile['data']) ? $profile['data'] : $profile;
        $lines = [];

        $name = $source['nombre'] ?? $source['name'] ?? $source['razon_social'] ?? null;
        if (is_string($name) && $name !== '') {
            $lines[] = ['label' => 'Aliado en Tu Dr. Group', 'value' => $name];
        }

        $limit = $source['limite_por_minuto'] ?? $source['limite'] ?? null;
        if (is_numeric($limit)) {
            $lines[] = ['label' => 'Límite por minuto', 'value' => (string) $limit];
        }

        $expires = $source['vencimiento'] ?? $source['vence_en'] ?? $source['expires_at'] ?? $source['acceso_vence_en'] ?? null;
        if (is_string($expires) && $expires !== '') {
            $lines[] = ['label' => 'Vencimiento del acceso', 'value' => $expires];
        }

        $permissions = $source['permisos'] ?? $source['permissions'] ?? null;
        $permissionLabel = self::permissionLabel($permissions);
        if ($permissionLabel !== null) {
            $lines[] = ['label' => 'Permisos', 'value' => $permissionLabel];
        }

        return $lines;
    }

    private static function permissionLabel(mixed $permissions): ?string
    {
        if (is_string($permissions) && $permissions !== '') {
            return $permissions;
        }

        if (! is_array($permissions)) {
            return null;
        }

        $labels = [];
        foreach ($permissions as $permission) {
            if (is_string($permission) && $permission !== '') {
                $labels[] = $permission;
            } elseif (is_scalar($permission)) {
                $labels[] = (string) $permission;
            }
        }

        return $labels === [] ? null : implode(', ', $labels);
    }
}
