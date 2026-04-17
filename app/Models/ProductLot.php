<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote asociado a una línea de compra: producto, factura del proveedor y vencimiento mm/YYYY.
 */
class ProductLot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'purchase_item_id',
        'product_id',
        'supplier_invoice_number',
        'expiration_month_year',
    ];

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
