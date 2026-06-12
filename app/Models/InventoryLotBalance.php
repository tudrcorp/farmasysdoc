<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Existencia remanente de un lote en una sucursal (entrada en compras, salida FEFO en ventas).
 */
class InventoryLotBalance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'product_lot_id',
        'product_id',
        'quantity_remaining',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_remaining' => 'decimal:3',
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
     * @return BelongsTo<ProductLot, $this>
     */
    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
