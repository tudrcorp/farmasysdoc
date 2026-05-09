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
        'client_id',
        'customer_invoice_reference',
        'delivery_address',
        'delivery_recipient_name',
        'delivery_recipient_phone',
        'delivery_notes',
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
     *
     * Traslados operativos generales (internos, externos, ajustes).
     */
    public static function automaticCodeForId(int $id): string
    {
        return 'TRAS-'.date('y').'000'.$id;
    }

    /**
     * Formato: TV-{año 2 dígitos}000{id} — solo registros con transfer_type «sale_transfer».
     *
     * Prefijo distinto de {@see automaticCodeForId()} para no confundirlos con traslados de inventario (TRAS-).
     */
    public static function automaticSaleTransferCodeForId(int $id): string
    {
        return 'TV-'.date('y').'000'.$id;
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
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliveryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }
}
