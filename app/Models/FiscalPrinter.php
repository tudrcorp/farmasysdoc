<?php

namespace App\Models;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalPrinterMode;
use App\Enums\FiscalPrinterModel;
use App\Support\Fiscal\HkaFlag21Formats;
use Database\Factories\FiscalPrinterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Máquina fiscal homologada por SENIAT, conectada a la PC de una caja.
 * El agente local de esa PC se autentica con {@see self::$agent_token_hash}.
 */
class FiscalPrinter extends Model
{
    /** @use HasFactory<FiscalPrinterFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'physical_cash_box_id',
        'name',
        'model',
        'serial_number',
        'fiscal_registry',
        'connection_port',
        'agent_token_hash',
        'is_active',
        'mode',
        'payment_slots',
        'command_format',
        'mode_changed_at',
        'mode_changed_by',
        'last_heartbeat_at',
        'last_status',
        'agent_version',
        'last_fiscal_number',
        'last_z_number',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'agent_token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'model' => FiscalPrinterModel::class,
            'is_active' => 'boolean',
            'mode' => FiscalPrinterMode::class,
            'payment_slots' => 'array',
            'command_format' => 'array',
            'mode_changed_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'last_status' => 'array',
        ];
    }

    public static function hashToken(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    /**
     * Token opaco para el encabezado Authorization: Bearer … del agente local.
     */
    public static function generatePlainToken(): string
    {
        return 'fd_fp_'.bin2hex(random_bytes(32));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PhysicalCashBox, $this>
     */
    public function physicalCashBox(): BelongsTo
    {
        return $this->belongsTo(PhysicalCashBox::class, 'physical_cash_box_id');
    }

    /**
     * @return HasMany<FiscalDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    public function isOnline(): bool
    {
        $threshold = max(30, (int) config('fiscal.agent.offline_after_seconds', 120));

        return $this->last_heartbeat_at !== null
            && $this->last_heartbeat_at->greaterThanOrEqualTo(now()->subSeconds($threshold));
    }

    public function currentMode(): FiscalPrinterMode
    {
        return $this->mode instanceof FiscalPrinterMode ? $this->mode : FiscalPrinterMode::Disabled;
    }

    /**
     * Configuración que el agente descarga en cada heartbeat y con cada trabajo.
     * payment_slots va como objeto para que sin medios asignados se serialice `{}` y no `[]` (el agente espera un diccionario).
     *
     * @return array{mode: string, fiscal_registry: string, payment_slots: object, command_format: ?array<string, mixed>}
     */
    public function agentConfig(): array
    {
        return [
            'mode' => $this->currentMode()->value,
            'fiscal_registry' => (string) $this->fiscal_registry,
            'payment_slots' => (object) array_filter(
                is_array($this->payment_slots) ? $this->payment_slots : [],
                fn (mixed $slot): bool => filled($slot),
            ),
            'command_format' => $this->agentCommandFormat(),
        ];
    }

    /**
     * Con flag 21 elegido se envía su tabla completa; si no, solo los campos con valor
     * y el agente completa el resto con el formato estándar HKA.
     *
     * @return array<string, mixed>|null
     */
    private function agentCommandFormat(): ?array
    {
        $stored = is_array($this->command_format) ? $this->command_format : [];

        $preset = HkaFlag21Formats::format(isset($stored['flag_21']) ? (string) $stored['flag_21'] : null);
        if ($preset !== null) {
            return $preset;
        }

        $format = array_filter($stored, fn (mixed $value): bool => filled($value));

        return $format === [] ? null : $format;
    }

    public function pendingDocumentsCount(): int
    {
        return $this->documents()
            ->whereIn('status', [
                FiscalDocumentStatus::Pending,
                FiscalDocumentStatus::Claimed,
                FiscalDocumentStatus::Printing,
            ])
            ->count();
    }
}
