<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCompany extends Model
{
    use HasFactory;

    /**
     * `assigned_credit_limit` almacena el saldo de crédito disponible (USD): se descuenta en
     * `partner_companies` al registrar cada consumo en `historical_of_movements`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'legal_name',
        'trade_name',
        'tax_id',
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
        'created_by',
        'updated_by',
        'date_created',
        'date_updated',
        'assigned_credit_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date_created' => 'date',
            'date_updated' => 'date',
            'assigned_credit_limit' => 'decimal:2',
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
     * Filas de vinculación usuario ↔ compañía (un aliado, N usuarios del panel).
     *
     * @return HasMany<PartnerCompanyUser, $this>
     */
    public function partnerCompanyUsers(): HasMany
    {
        return $this->hasMany(PartnerCompanyUser::class);
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
