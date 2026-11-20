<?php

namespace App\Models;

use Database\Factories\PartnerUncoveredMedicineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copia local de un medicamento no cubierto que Tu Dr. Group está gestionando.
 *
 * Nombre, cédula y teléfono del paciente van cifrados. Este modelo no se audita
 * para no copiar la cédula al registro de auditoría.
 */
class PartnerUncoveredMedicine extends Model
{
    /** @use HasFactory<PartnerUncoveredMedicineFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'external_id',
        'case_number',
        'coordination_id',
        'coordination_reference',
        'coordination_status',
        'patient_name',
        'patient_national_id',
        'patient_phone',
        'patient_state',
        'patient_city',
        'medicine_name',
        'indications',
        'quantity_label',
        'duration_label',
        'priority',
        'management_status',
        'covered',
        'inventory_status',
        'registered_at',
        'remote_updated_at',
        'synced_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'patient_name',
        'patient_national_id',
        'patient_phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'coordination_id' => 'integer',
            'patient_name' => 'encrypted',
            'patient_national_id' => 'encrypted',
            'patient_phone' => 'encrypted',
            'covered' => 'boolean',
            'registered_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPartner(Builder $query, int $partnerCompanyId): Builder
    {
        return $query->where('partner_company_id', $partnerCompanyId);
    }

    public function patientLocation(): ?string
    {
        $location = trim(implode(', ', array_filter([
            $this->patient_city,
            $this->patient_state,
        ])));

        return $location === '' ? null : $location;
    }
}
