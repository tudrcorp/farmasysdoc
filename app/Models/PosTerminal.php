<?php

namespace App\Models;

use App\Enums\VenezuelanPagoMovilBank;
use Database\Factories\PosTerminalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    /** @use HasFactory<PosTerminalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'code',
        'bank_code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function bank(): ?VenezuelanPagoMovilBank
    {
        if (! filled($this->bank_code)) {
            return null;
        }

        return VenezuelanPagoMovilBank::tryFrom((string) $this->bank_code);
    }

    public function bankLabel(): string
    {
        return $this->bank()?->optionLabel() ?? (filled($this->bank_code) ? (string) $this->bank_code : '—');
    }

    public function displayLabel(): string
    {
        return $this->bankLabel().' · '.$this->code;
    }
}
