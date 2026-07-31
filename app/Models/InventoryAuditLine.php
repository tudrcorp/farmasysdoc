<?php

namespace App\Models;

use App\Enums\InventoryAuditLineStatus;
use Database\Factories\InventoryAuditLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InventoryAuditLine extends Model
{
    /** @use HasFactory<InventoryAuditLineFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_audit_id',
        'inventory_id',
        'product_id',
        'branch_id',
        'status',
        'system_quantity',
        'system_cost_price',
        'counted_quantity',
        'new_cost_price',
        'quantity_delta',
        'cost_changed',
        'inventory_movement_id',
        'processed_by',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryAuditLineStatus::class,
            'system_quantity' => 'decimal:3',
            'system_cost_price' => 'decimal:2',
            'counted_quantity' => 'decimal:3',
            'new_cost_price' => 'decimal:2',
            'quantity_delta' => 'decimal:3',
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
     * @return BelongsTo<Inventory, $this>
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * @return BelongsTo<InventoryMovement, $this>
     */
    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    /**
     * @return HasOne<InventoryAuditUpdate, $this>
     */
    public function auditUpdate(): HasOne
    {
        return $this->hasOne(InventoryAuditUpdate::class);
    }

    /**
     * @return MorphMany<InventoryMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference');
    }

    public function isPending(): bool
    {
        return $this->status === InventoryAuditLineStatus::Pending;
    }
}
