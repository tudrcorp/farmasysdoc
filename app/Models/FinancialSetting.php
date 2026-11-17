<?php

namespace App\Models;

use App\Support\Finance\DefaultIgtfRate;
use App\Support\Finance\DefaultVatRate;
use App\Support\Finance\VatRetentionAgentPolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros financieros globales (fila única id = 1).
 */
class FinancialSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'default_vat_rate_percent',
        'igtf_rate_percent',
        'vat_retention_agent_active',
        'vat_retention_suspended_from',
        'vat_retention_resumed_from',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            DefaultVatRate::forgetCache();
            DefaultIgtfRate::forgetCache();
            VatRetentionAgentPolicy::forgetCache();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_vat_rate_percent' => 'decimal:2',
            'igtf_rate_percent' => 'decimal:2',
            'vat_retention_agent_active' => 'boolean',
            'vat_retention_suspended_from' => 'date',
            'vat_retention_resumed_from' => 'date',
        ];
    }

    /**
     * Registro único de configuración financiera.
     */
    public static function current(): self
    {
        $fallback = (float) config('orders.default_vat_rate_percent', 16);

        /** @var self */
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['default_vat_rate_percent' => $fallback],
        );
    }
}
