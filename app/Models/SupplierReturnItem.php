<?php

namespace App\Models;

use Database\Factories\SupplierReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    /** @use HasFactory<SupplierReturnItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_return_id',
        'product_id',
        'purchase_item_id',
        'barcode',
        'product_name',
        'quantity_invoiced',
        'quantity_received',
        'quantity_excess',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_invoiced' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'quantity_excess' => 'decimal:3',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SupplierReturn, $this>
     */
    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
