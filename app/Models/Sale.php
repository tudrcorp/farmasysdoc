<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sale_number',
        'branch_id',
        'client_id',
        'status',
        'subtotal',
        'tax_total',
        'igtf_total',
        'discount_total',
        'total',
        'payment_method',
        'payment_usd',
        'payment_ves',
        'bcv_ves_per_usd',
        'efectivo_usd_caja_meta',
        'reference',
        'payment_status',
        'notes',
        'sold_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'igtf_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_usd' => 'decimal:2',
            'payment_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
            'efectivo_usd_caja_meta' => 'array',
            'sold_at' => 'datetime',
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Cuenta por cobrar generada desde la venta (p. ej. caja a crédito).
     *
     * @return HasOne<AccountsReceivable, $this>
     */
    public function accountsReceivable(): HasOne
    {
        return $this->hasOne(AccountsReceivable::class);
    }

    /**
     * Movimientos de caja física vinculados a la venta (vueltos en USD, etc.).
     *
     * @return HasMany<PhysicalCashBoxMovement, $this>
     */
    public function physicalCashBoxMovements(): HasMany
    {
        return $this->hasMany(PhysicalCashBoxMovement::class);
    }
}
