<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Caja física del cajero: efectivo para vueltos (montos USD y VES).
 * Relación uno a uno con {@see User}; en dominio aplica a usuarios con rol CAJERO.
 *
 * @property-read User $user
 */
class PhysicalCashBox extends Model
{
    use HasFactory;

    protected $table = 'cajas_fisicas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'amount_usd',
        'amount_ves',
        'is_open',
        'opened_at',
        'closed_at',
        'close_usd_cash_photo_path',
        'close_pos_receipt_photo_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'amount_ves' => 'decimal:2',
            'is_open' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PhysicalCashBoxMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(PhysicalCashBoxMovement::class, 'physical_cash_box_id');
    }
}
