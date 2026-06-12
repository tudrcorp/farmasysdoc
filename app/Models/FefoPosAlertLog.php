<?php

namespace App\Models;

use App\Enums\FefoPosAlertSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta FEFO mostrada en caja y su eventual vinculación con una venta.
 *
 * @property-read Branch $branch
 * @property-read User $user
 * @property-read Product $product
 * @property-read ProductLot $productLot
 * @property-read Sale|null $sale
 */
class FefoPosAlertLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'user_id',
        'product_id',
        'product_lot_id',
        'product_code',
        'product_name',
        'expiration_month_year',
        'severity',
        'days_until_expiry',
        'quantity_in_lot',
        'supplier_invoice_number',
        'notified_at',
        'sale_id',
        'sale_number',
        'quantity_sold',
        'sold_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => FefoPosAlertSeverity::class,
            'days_until_expiry' => 'integer',
            'quantity_in_lot' => 'decimal:3',
            'quantity_sold' => 'decimal:3',
            'notified_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function isLinkedToSale(): bool
    {
        return $this->sale_id !== null;
    }

    public function minutesUntilSale(): ?int
    {
        if (! $this->isLinkedToSale() || $this->sold_at === null || $this->notified_at === null) {
            return null;
        }

        return (int) $this->notified_at->diffInMinutes($this->sold_at, false);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductLot, $this>
     */
    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
