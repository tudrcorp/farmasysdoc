<?php

namespace App\Models;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Support\Orders\PartnerMedicineCharge;
use App\Support\Partners\AgreementTermStatus;
use Database\Factories\PartnerCompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PartnerCompany extends Model
{
    /** @use HasFactory<PartnerCompanyFactory> */
    use HasFactory;

    private ?bool $governedByAgreementsCache = null;

    /**
     * `assigned_credit_limit` almacena el saldo de crédito disponible (USD): se descuenta en
     * `partner_companies` al registrar cada consumo en `historical_of_movements`.
     * `credit_limit_usd` es el límite de crédito aprobado (tope), independiente del saldo.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'legal_name',
        'trade_name',
        'tax_id',
        'client_type',
        'payment_modality',
        'credit_limit_usd',
        'email',
        'phone',
        'mobile_phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'contact_name',
        'contact_email',
        'contact_phone',
        'agreement_reference',
        'agreement_terms',
        'notes',
        'is_active',
        'can_place_orders',
        'created_by',
        'updated_by',
        'date_created',
        'date_updated',
        'agreement_expires_on',
        'assigned_credit_limit',
        'profit_percentage_a',
        'profit_percentage_b',
        'discount_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_place_orders' => 'boolean',
            'client_type' => PartnerCompanyClientType::class,
            'payment_modality' => PartnerCompanyPaymentModality::class,
            'credit_limit_usd' => 'decimal:2',
            'date_created' => 'date',
            'date_updated' => 'date',
            'agreement_expires_on' => 'date',
            'assigned_credit_limit' => 'decimal:2',
            'profit_percentage_a' => 'decimal:6',
            'profit_percentage_b' => 'decimal:6',
            'discount_percentage' => 'decimal:6',
        ];
    }

    /**
     * Código interno visible en listados: ALDO-{año actual}-00{id del aliado}.
     */
    public static function formatCode(int|string $id): string
    {
        return 'ALDO-'.now()->format('Y').'-00'.$id;
    }

    /**
     * @return HasMany<OrderService, $this>
     */
    public function orderServices(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }

    /**
     * Relación 1:N: un aliado puede tener muchas órdenes; cada orden referencia a lo sumo un aliado.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Movimientos de consumo de crédito registrados para esta compañía.
     *
     * @return HasMany<HistoricalOfMovement, $this>
     */
    public function historicalOfMovements(): HasMany
    {
        return $this->hasMany(HistoricalOfMovement::class);
    }

    /**
     * Suma de montos consumidos registrados en histórico (pedidos a crédito finalizados).
     */
    public function totalCreditConsumedAmount(): float
    {
        return round((float) $this->historicalOfMovements()->sum('total_cost'), 2);
    }

    /**
     * Saldo disponible en USD (reflejado en `assigned_credit_limit`).
     */
    public function remainingCreditAmount(): float
    {
        return max(0.0, round((float) ($this->assigned_credit_limit ?? 0), 2));
    }

    /**
     * Indica si el aliado tiene cupo de crédito configurado (control de consumo y movimientos).
     */
    public function managesAssignedCredit(): bool
    {
        return $this->assigned_credit_limit !== null;
    }

    /**
     * Tope de línea (disponible + ya consumido según histórico), para mostrar contexto en UI.
     */
    public function totalCreditCeilingAmount(): float
    {
        return round($this->remainingCreditAmount() + $this->totalCreditConsumedAmount(), 2);
    }

    /**
     * Sucursales donde opera el convenio con el aliado.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_partner_company')->withTimestamps();
    }

    /**
     * Contactos y responsables del aliado.
     *
     * @return HasMany<PartnerCompanyContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerCompanyContact::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Expediente documental del aliado (carta de convenio, RIF, cédula del representante, Registro Mercantil…).
     *
     * @return HasMany<PartnerCompanyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(PartnerCompanyDocument::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Historial de convenios (todas las versiones).
     *
     * @return HasMany<PartnerCompanyAgreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(PartnerCompanyAgreement::class);
    }

    /**
     * Planes corporativos asignados al aliado.
     *
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)
            ->using(PartnerCompanyPlan::class)
            ->withPivot(PartnerCompanyPlan::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    /**
     * Porcentaje que se aplica al costo de cada medicamento despachado o vendido a este aliado.
     * Si un plan activo asignado lo define, ese porcentaje manda. Si no, se usa el porcentaje libre del aliado.
     */
    public function medicineDispatchProfitPercent(): float
    {
        return PartnerMedicineCharge::forCompany($this)->profitPercent;
    }

    /**
     * Convenio vigente (como máximo uno).
     *
     * @return HasOne<PartnerCompanyAgreement, $this>
     */
    public function currentAgreement(): HasOne
    {
        return $this->hasOne(PartnerCompanyAgreement::class)
            ->where('status', PartnerAgreementStatus::Active->value)
            ->latestOfMany('starts_on');
    }

    /**
     * Vigencia del convenio según la fecha de creación y la de vencimiento.
     */
    public function agreementTermStatus(): AgreementTermStatus
    {
        return AgreementTermStatus::fromDates($this->date_created, $this->agreement_expires_on);
    }

    /**
     * Cuando el aliado ya opera con convenios, sus condiciones solo cambian desde «Convenios y renovaciones».
     */
    public function isGovernedByAgreements(): bool
    {
        return $this->governedByAgreementsCache ??= $this->agreements()
            ->whereIn('status', [
                PartnerAgreementStatus::Approved->value,
                PartnerAgreementStatus::Active->value,
                PartnerAgreementStatus::Expired->value,
                PartnerAgreementStatus::Superseded->value,
            ])
            ->exists();
    }

    /**
     * Indica si la modalidad pactada opera con límite de crédito (crédito o integral).
     */
    public function usesCreditLimit(): bool
    {
        return $this->payment_modality?->usesCreditLimit() ?? false;
    }

    /**
     * Filas de vinculación usuario ↔ compañía (un aliado, N usuarios del panel).
     *
     * @return HasMany<PartnerCompanyUser, $this>
     */
    public function partnerCompanyUsers(): HasMany
    {
        return $this->hasMany(PartnerCompanyUser::class);
    }

    /**
     * Token y estado de la consulta de medicamentos no cubiertos en Tu Dr. Group.
     *
     * @return HasOne<PartnerUncoveredMedicineConnection, $this>
     */
    public function uncoveredMedicineConnection(): HasOne
    {
        return $this->hasOne(PartnerUncoveredMedicineConnection::class);
    }

    /**
     * Copia local de los medicamentos no cubiertos que Tu Dr. Group está gestionando.
     *
     * @return HasMany<PartnerUncoveredMedicine, $this>
     */
    public function uncoveredMedicines(): HasMany
    {
        return $this->hasMany(PartnerUncoveredMedicine::class);
    }

    public function hasUncoveredMedicinesAccess(): bool
    {
        return (bool) $this->uncoveredMedicineConnection?->hasToken();
    }

    /**
     * Usuarios del panel asociados a esta compañía a través de `partner_company_users`.
     *
     * @return BelongsToMany<User, $this>
     */
    public function alliedPanelUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_company_users')
            ->withTimestamps();
    }
}
