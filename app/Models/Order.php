<?php

namespace App\Models;

use App\Enums\ConvenioType;
use App\Enums\OrderFulfillmentType;
use App\Enums\OrderPartnerCashPaymentMethod;
use App\Enums\OrderPartnerPaymentTerms;
use App\Enums\OrderStatus;
use App\Support\Orders\PartnerOrderDeliverySync;
use App\Support\Partners\PartnerCreditLedger;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            if ($order->partner_company_id !== null) {
                $code = PartnerCompany::query()->whereKey($order->partner_company_id)->value('code');
                $order->partner_company_code = $code;
            } elseif ($order->isDirty('partner_company_id')) {
                $order->partner_company_code = null;
            }
        });

        static::saved(function (Order $order): void {
            PartnerOrderDeliverySync::sync($order);
        });

        static::updated(function (Order $order): void {
            if (! $order->wasChanged('status')) {
                return;
            }

            if ($order->status !== OrderStatus::InProgress) {
                return;
            }

            PartnerCreditLedger::recordConsumptionIfApplicable($order);
        });

        static::created(function (Order $order): void {
            if ($order->status !== OrderStatus::InProgress) {
                return;
            }

            PartnerCreditLedger::recordConsumptionIfApplicable($order);
        });

        static::deleting(function (Order $order): void {
            PartnerOrderDeliverySync::removePartnerDelivery($order);
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'client_id',
        'branch_id',
        'partner_company_id',
        'partner_company_code',
        'is_wholesale',
        'partner_fulfillment_type',
        'partner_payment_terms',
        'partner_cash_payment_method',
        'partner_pago_movil_reference',
        'partner_zelle_reference_name',
        'partner_zelle_transaction_number',
        'partner_cash_payment_proof_path',
        'status',
        'convenio_type',
        'convenio_partner_name',
        'convenio_reference',
        'convenio_notes',
        'delivery_recipient_name',
        'delivery_phone',
        'delivery_recipient_document',
        'delivery_address',
        'delivery_city',
        'delivery_state',
        'delivery_notes',
        'scheduled_delivery_at',
        'dispatched_at',
        'delivered_at',
        'delivery_fulfillment_duration_minutes',
        'partner_delivery_rating',
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
            'is_wholesale' => 'boolean',
            'status' => OrderStatus::class,
            'convenio_type' => ConvenioType::class,
            'partner_fulfillment_type' => OrderFulfillmentType::class,
            'partner_payment_terms' => OrderPartnerPaymentTerms::class,
            'partner_cash_payment_method' => OrderPartnerCashPaymentMethod::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'scheduled_delivery_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delivery_fulfillment_duration_minutes' => 'integer',
            'partner_delivery_rating' => 'integer',
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
     * Lado N de la relación 1:N con el aliado. `null` = pedido interno sin compañía aliada.
     *
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Entregas generadas automáticamente para pedidos aliado con envío a domicilio (`delivery_type` = partner_delivery).
     *
     * @return HasMany<Delivery, $this>
     */
    public function partnerDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->where('delivery_type', PartnerOrderDeliverySync::DELIVERY_TYPE_PARTNER);
    }

    /**
     * Usuario de reparto vinculado a la entrega aliada (p. ej. tras «Iniciar entrega»), para mostrar foto al aliado.
     */
    public function deliveryAssigneeUser(): ?User
    {
        if ($this->relationLoaded('partnerDeliveries')) {
            $delivery = $this->partnerDeliveries
                ->filter(fn (Delivery $d): bool => $d->user_id !== null)
                ->sortByDesc(static fn (Delivery $d): int => (int) $d->getKey())
                ->first();

            return $delivery?->user;
        }

        $delivery = $this->partnerDeliveries()
            ->whereNotNull('user_id')
            ->with('user')
            ->orderByDesc('id')
            ->first();

        return $delivery?->user;
    }

    /**
     * Minutos entre creación del pedido y fecha de entrega (p. ej. al cerrar con evidencia).
     */
    public static function computeFulfillmentDurationMinutes(?\DateTimeInterface $createdAt, ?\DateTimeInterface $deliveredAt): ?int
    {
        if ($createdAt === null || $deliveredAt === null) {
            return null;
        }

        $start = Carbon::parse($createdAt);
        $end = Carbon::parse($deliveredAt);

        if ($end->lessThan($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Ruta en disco público de la foto de evidencia cargada al cerrar la entrega (tabla `deliveries`, tipo aliado).
     */
    public function partnerDeliveryEvidencePath(): ?string
    {
        if ($this->relationLoaded('partnerDeliveries')) {
            $row = $this->partnerDeliveries
                ->filter(fn (Delivery $d): bool => filled($d->delivery_evidence_path))
                ->sortByDesc(static fn (Delivery $d): int => (int) $d->getKey())
                ->first();

            return filled($row?->delivery_evidence_path) ? (string) $row->delivery_evidence_path : null;
        }

        $path = $this->partnerDeliveries()
            ->whereNotNull('delivery_evidence_path')
            ->orderByDesc('id')
            ->value('delivery_evidence_path');

        return filled($path) ? (string) $path : null;
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

    /**
     * Sincroniza subtotal, impuestos, descuentos y total del encabezado con las líneas persistidas.
     */
    public function recalculateTotalsFromItems(): void
    {
        $this->loadMissing('items');

        $subtotal = round((float) $this->items->sum('line_subtotal'), 2);
        $taxTotal = round((float) $this->items->sum('tax_amount'), 2);
        $discountTotal = round((float) $this->items->sum('discount_amount'), 2);
        $total = round((float) $this->items->sum('line_total'), 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total' => $total,
        ])->saveQuietly();
    }
}
