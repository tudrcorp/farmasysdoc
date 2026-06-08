<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Falla de existencia registrada desde la caja registradora (producto con stock 0).
 *
 * @property-read Branch $branch
 * @property-read Product $product
 * @property-read User $user
 */
class InventoryStockFailure extends Model
{
    protected $table = 'fallas_existencia';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'product_id',
        'user_id',
        'product_code',
        'product_name',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
