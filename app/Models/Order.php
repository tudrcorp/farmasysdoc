<?php

namespace App\Models;

use App\Enums\ConvenioType;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'client_id',
        'branch_id',
        'partner_company_code',
        'status',
        'convenio_type',
        'convenio_partner_name',
        'convenio_reference',
        'convenio_notes',
        'delivery_recipient_name',
        'delivery_phone',
        'delivery_address',
        'delivery_city',
        'delivery_state',
        'delivery_notes',
        'scheduled_delivery_at',
        'dispatched_at',
        'delivered_at',
        'delivery_assignee',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'convenio_type' => ConvenioType::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'scheduled_delivery_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Formato: PED-{año}-000{id} (ej. PED-2026-00015).
     */
    public static function formatOrderNumber(int $id, ?\DateTimeInterface $at = null): string
    {
        $at ??= now();

        return 'PED-'.$at->format('Y').'-000'.$id;
    }

    /**
     * Asigna el número canónico según el id y la fecha de creación (sin disparar eventos).
     */
    public function assignCanonicalOrderNumber(): void
    {
        $this->forceFill([
            'order_number' => static::formatOrderNumber((int) $this->id, $this->created_at),
        ])->saveQuietly();
    }
}
