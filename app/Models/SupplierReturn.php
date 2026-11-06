<?php

namespace App\Models;

use App\Enums\SupplierReturnStatus;
use App\Services\Purchases\SupplierReturnWorkflow;
use Database\Factories\SupplierReturnFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Devolución de mercancía recibida de más. Solo cambia de estado vía
 * {@see SupplierReturnWorkflow}; no toca inventario.
 *
 * @property SupplierReturnStatus $status
 */
class SupplierReturn extends Model
{
    /** @use HasFactory<SupplierReturnFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'supplier_id',
        'purchase_id',
        'received_on',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'por-revisar',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierReturnStatus::class,
            'received_on' => 'immutable_date',
            'returned_on' => 'immutable_date',
            'reviewed_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
        ];
    }

    public static function formatNumber(int|string $id): string
    {
        return 'DEV-'.now()->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return HasMany<SupplierReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<SupplierReturnAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupplierReturnAttachment::class)->orderBy('id');
    }

    /**
     * @return HasMany<SupplierReturnEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SupplierReturnEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * @param  Builder<SupplierReturn>  $query
     * @return Builder<SupplierReturn>
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', SupplierReturnStatus::PendingReview->value);
    }

    public function totalExcess(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return round((float) $items->sum('quantity_excess'), 3);
    }

    public function supplierName(): string
    {
        return (string) ($this->supplier?->trade_name ?: $this->supplier?->legal_name ?: '—');
    }
}
