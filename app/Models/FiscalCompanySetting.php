<?php

namespace App\Models;

use App\Support\Fiscal\CompanyFiscalAddress;
use Illuminate\Database\Eloquent\Model;

/**
 * Identidad fiscal de la empresa principal (fila única id = 1).
 */
class FiscalCompanySetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'address',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            CompanyFiscalAddress::forgetCache();
        });
    }

    /**
     * Registro único de la dirección fiscal de la empresa.
     */
    public static function current(): self
    {
        $fallback = trim((string) config('fiscal.retention_agent.address', ''));

        /** @var self */
        return self::query()->firstOrCreate(
            ['id' => 1],
            ['address' => $fallback !== '' ? $fallback : null],
        );
    }
}
