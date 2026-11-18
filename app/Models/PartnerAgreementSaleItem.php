<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una venta por convenio.
 */
class PartnerAgreementSaleItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_agreement_sale_id',
        'product_id',
        'inventory_id',
        'quantity',
        'unit_price',
        'line_subtotal',
        'tax_amount',
        'line_total',
        'unit_cost',
        'product_name_snapshot',
        'sku_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PartnerAgreementSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PartnerAgreementSale::class, 'partner_agreement_sale_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
