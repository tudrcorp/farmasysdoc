<?php

namespace App\Models;

use App\Enums\ConciliationCacheaCollectionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliationCachea extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'user_id',
        'sale_id',
        'sale_number',
        'sale_total',
        'cachea_paid_amount',
        'remainder',
        'complement_payment_method',
        'reference',
        'collection_status',
        'collection_status_at',
        'collection_status_by',
        'recorded_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_total' => 'decimal:2',
            'cachea_paid_amount' => 'decimal:2',
            'remainder' => 'decimal:2',
            'collection_status' => ConciliationCacheaCollectionStatus::class,
            'collection_status_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ConciliationCachea>  $query
     * @return Builder<ConciliationCachea>
     */
    public function scopePendingCollection(Builder $query): Builder
    {
        return $query->where(
            'collection_status',
            ConciliationCacheaCollectionStatus::PendingCollection,
        );
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
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
