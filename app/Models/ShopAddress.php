<?php

namespace App\Models;

use Database\Factories\ShopAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pwa_customer_id',
    'label',
    'address_line',
    'city',
    'state',
    'reference',
    'is_primary',
])]
class ShopAddress extends Model
{
    /** @use HasFactory<ShopAddressFactory> */
    use HasFactory;

    protected $table = 'pwa_addresses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShopCustomer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(ShopCustomer::class, 'pwa_customer_id');
    }

    public function title(): string
    {
        return filled($this->label) ? trim((string) $this->label) : 'Dirección';
    }

    public function summary(): string
    {
        return collect([$this->address_line, $this->city, $this->state])
            ->filter(fn (mixed $part): bool => filled($part))
            ->implode(' · ');
    }
}
