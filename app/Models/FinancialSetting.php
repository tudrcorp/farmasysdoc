<?php

namespace App\Models;

use App\Support\Finance\DefaultIgtfRate;
use App\Support\Finance\DefaultVatRate;
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
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            DefaultVatRate::forgetCache();
            DefaultIgtfRate::forgetCache();
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
