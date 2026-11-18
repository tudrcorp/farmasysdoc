<?php

namespace App\Models;

use App\Enums\PartnerFundMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del fondo de consumo de un aliado en un plan Flexible. `amount_usd` lleva signo:
 * negativo para consumos, positivo para devoluciones y recargas. `balance_after_usd` es el
 * disponible después del movimiento.
 */
class PartnerFundMovement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_plan_id',
        'partner_company_id',
        'type',
        'amount_usd',
        'amount_ves',
        'bcv_ves_per_usd',
        'balance_after_usd',
        'partner_agreement_sale_id',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartnerFundMovementType::class,
            'amount_usd' => 'decimal:2',
            'amount_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:4',
            'balance_after_usd' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PartnerCompanyPlan, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PartnerCompanyPlan::class, 'partner_company_plan_id');
    }

    /**
     * @return BelongsTo<PartnerAgreementSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PartnerAgreementSale::class, 'partner_agreement_sale_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
