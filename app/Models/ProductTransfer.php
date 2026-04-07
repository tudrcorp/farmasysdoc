<?php

namespace App\Models;

use App\Enums\ProductTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductTransfer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'from_branch_id',
        'to_branch_id',
        'status',
        'transfer_type',
        'created_by',
        'updated_by',
        'total_transfer_cost',
        'completed_by',
        'completed_at',
        'sale_id',
        'delivery_user_id',
        'in_progress_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductTransferStatus::class,
            'total_transfer_cost' => 'decimal:2',
            'completed_at' => 'datetime',
            'in_progress_at' => 'datetime',
        ];
    }

    /**
     * Formato: TRAS-{año 2 dígitos}000{id}, p. ej. TRAS-260001 para id 1 en 2026.
     */
    public static function automaticCodeForId(int $id): string
    {
        return 'TRAS-'.date('y').'000'.$id;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return HasMany<ProductTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductTransferItem::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliveryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }
}
