<?php

namespace App\Models;

use Database\Factories\OrderAcceptanceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAcceptanceItem extends Model
{
    /** @use HasFactory<OrderAcceptanceItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_acceptance_id',
        'order_item_id',
        'product_id',
        'product_name',
        'barcode',
        'sku',
        'quantity_ordered',
        'quantity_on_hand',
        'accepted',
        'rejection_note',
        'unit_price_usd',
        'line_total_usd',
        'line_total_ves',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_on_hand' => 'decimal:3',
            'accepted' => 'boolean',
            'unit_price_usd' => 'decimal:2',
            'line_total_usd' => 'decimal:2',
            'line_total_ves' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OrderAcceptance, $this>
     */
    public function acceptance(): BelongsTo
    {
        return $this->belongsTo(OrderAcceptance::class, 'order_acceptance_id');
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
