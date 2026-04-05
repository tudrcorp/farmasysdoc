<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Delivery $delivery): void {
            if ($delivery->order_id !== null && blank($delivery->order_number)) {
                $delivery->order_number = Order::query()
                    ->whereKey($delivery->order_id)
                    ->value('order_number');
            }
        });
    }

    protected $fillable = [
        'branch_id',
        'order_id',
        'order_number',
        'user_id',
        'delivery_type',
        'status',
        'taken_by',
        'order_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'order_snapshot' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
