<?php

namespace App\Models;

use Database\Factories\OrderAcceptanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderAcceptance extends Model
{
    /** @use HasFactory<OrderAcceptanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'partner_company_id',
        'accepted_by',
        'accepted_by_name',
        'accepted_at',
        'bcv_ves_per_usd',
        'accepted_total_usd',
        'accepted_total_ves',
        'delivery_note_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'bcv_ves_per_usd' => 'decimal:2',
            'accepted_total_usd' => 'decimal:2',
            'accepted_total_ves' => 'decimal:2',
        ];
    }

    public static function numberFor(int $id): string
    {
        return 'NEP-'.str_pad((string) $id, 7, '0', STR_PAD_LEFT);
    }

    public function fileName(): string
    {
        return 'nota-entrega-'.($this->delivery_note_number ?? $this->getKey()).'.pdf';
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * @return HasMany<OrderAcceptanceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderAcceptanceItem::class);
    }
}
