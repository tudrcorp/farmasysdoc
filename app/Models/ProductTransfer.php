<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTransfer extends Model
{
    protected $fillable = [
        'code',
        'product_id',
        'from_branch_id',
        'to_branch_id',
        'quantity',
        'status',
        'transfer_type',
        'created_by',
        'updated_by',
    ];

    /**
     * Formato: TRAS-{año 2 dígitos}000{id}, p. ej. TRAS-260001 para id 1 en 2026.
     */
    public static function automaticCodeForId(int $id): string
    {
        return 'TRAS-'.date('y').'000'.$id;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }
}
