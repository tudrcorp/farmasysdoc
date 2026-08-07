<?php

namespace App\Models;

use App\Enums\VenezuelanPagoMovilBank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBankAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'bank_code',
        'account_number',
        'phone',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        $account = filled($this->account_number)
            ? (string) $this->account_number
            : 'Sin número';

        return $this->bankLabel().' · '.$account;
    }
}
