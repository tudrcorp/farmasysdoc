<?php

namespace App\Models;

use App\Enums\SupplierReturnEventType;
use App\Enums\SupplierReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora inmutable de una devolución.
 */
class SupplierReturnEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_return_id',
        'type',
        'from_status',
        'to_status',
        'user_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SupplierReturnEventType::class,
            'from_status' => SupplierReturnStatus::class,
            'to_status' => SupplierReturnStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
