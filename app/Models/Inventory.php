<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'reorder_point',
        'minimum_stock',
        'maximum_stock',
        'storage_location',
        'allow_negative_stock',
        'last_movement_at',
        'last_stock_take_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'allow_negative_stock' => 'boolean',
            'last_movement_at' => 'datetime',
            'last_stock_take_at' => 'datetime',
        ];
    }

    /**
     * Cantidad disponible para venta (existencias menos reservado).
     *
     * @return Attribute<float, never>
     */
    protected function availableQuantity(): Attribute
    {
        return Attribute::get(function (): float {
            $available = (float) $this->quantity - (float) $this->reserved_quantity;

            if (! $this->allow_negative_stock) {
                return max(0.0, $available);
            }

            return $available;
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
