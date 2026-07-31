<?php

namespace App\Models;

use Database\Factories\InventoryAuditUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAuditUpdate extends Model
{
    /** @use HasFactory<InventoryAuditUpdateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_audit_id',
        'inventory_audit_line_id',
        'branch_id',
        'product_id',
        'product_sku',
        'product_barcode',
        'product_name',
        'branch_name',
        'previous_quantity',
        'new_quantity',
        'quantity_delta',
        'previous_cost_price',
        'new_cost_price',
        'quantity_changed',
        'cost_changed',
        'processed_by',
        'processed_by_name',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_quantity' => 'decimal:3',
            'new_quantity' => 'decimal:3',
            'quantity_delta' => 'decimal:3',
            'previous_cost_price' => 'decimal:2',
            'new_cost_price' => 'decimal:2',
            'quantity_changed' => 'boolean',
            'cost_changed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InventoryAudit, $this>
     */
    public function inventoryAudit(): BelongsTo
    {
        return $this->belongsTo(InventoryAudit::class);
    }

    /**
     * @return BelongsTo<InventoryAuditLine, $this>
     */
    public function inventoryAuditLine(): BelongsTo
    {
        return $this->belongsTo(InventoryAuditLine::class);
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
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
