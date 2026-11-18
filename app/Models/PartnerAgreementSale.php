<?php

namespace App\Models;

use App\Enums\PartnerAgreementSaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta por convenio (plan Flexible): se registra en caja, descuenta inventario y el fondo del
 * aliado, y emite una nota de entrega a nombre del afiliado. No es una venta fiscal: no está en
 * `sales`, no genera factura ni afecta el cierre de caja. Forma de pago: «Por convenio».
 */
class PartnerAgreementSale extends Model
{
    public const PAYMENT_LABEL = 'Por convenio';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'branch_id',
        'partner_company_id',
        'plan_id',
        'partner_company_plan_id',
        'partner_plan_affiliate_id',
        'client_id',
        'client_name',
        'client_document',
        'client_phone',
        'client_email',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'bcv_ves_per_usd',
        'total_ves',
        'fund_balance_after_usd',
        'notes',
        'sold_at',
        'sold_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'emailed_at',
        'whatsapped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PartnerAgreementSaleStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:4',
            'total_ves' => 'decimal:2',
            'fund_balance_after_usd' => 'decimal:2',
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'emailed_at' => 'datetime',
            'whatsapped_at' => 'datetime',
        ];
    }

    public static function numberFor(int $id): string
    {
        return 'NE-'.str_pad((string) $id, 7, '0', STR_PAD_LEFT);
    }

    public function isCancelled(): bool
    {
        return $this->status === PartnerAgreementSaleStatus::Cancelled;
    }

    public function fileName(): string
    {
        return 'nota-entrega-'.($this->number ?? $this->getKey()).'.pdf';
    }

    /**
     * @return HasMany<PartnerAgreementSaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PartnerAgreementSaleItem::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<PartnerCompanyPlan, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PartnerCompanyPlan::class, 'partner_company_plan_id');
    }

    /**
     * @return BelongsTo<PartnerPlanAffiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(PartnerPlanAffiliate::class, 'partner_plan_affiliate_id');
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
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
