<?php

namespace App\Models;

use App\Enums\InventoryAuditLineStatus;
use App\Enums\InventoryAuditStatus;
use Database\Factories\InventoryAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAudit extends Model
{
    /** @use HasFactory<InventoryAuditFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'status',
        'started_by',
        'closed_by',
        'started_at',
        'closed_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InventoryAuditStatus::class,
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<InventoryAuditLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAuditLine::class);
    }

    /**
     * @return HasMany<InventoryAuditUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(InventoryAuditUpdate::class);
    }

    public function isOpen(): bool
    {
        return $this->status === InventoryAuditStatus::Open;
    }

    public function pendingLinesCount(): int
    {
        return $this->lines()
            ->where('status', InventoryAuditLineStatus::Pending)
            ->count();
    }

    /**
     * @return array{total: int, pending: int, verified: int, updated: int, processed: int}
     */
    public function progressCounts(): array
    {
        $pending = $this->lines()->where('status', InventoryAuditLineStatus::Pending)->count();
        $verified = $this->lines()->where('status', InventoryAuditLineStatus::Verified)->count();
        $updated = $this->lines()->where('status', InventoryAuditLineStatus::Updated)->count();
        $total = $pending + $verified + $updated;

        return [
            'total' => $total,
            'pending' => $pending,
            'verified' => $verified,
            'updated' => $updated,
            'processed' => $verified + $updated,
        ];
    }
}
