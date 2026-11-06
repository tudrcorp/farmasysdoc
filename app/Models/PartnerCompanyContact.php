<?php

namespace App\Models;

use Database\Factories\PartnerCompanyContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerCompanyContact extends Model
{
    /** @use HasFactory<PartnerCompanyContactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_company_id',
        'name',
        'position',
        'email',
        'phone',
        'is_responsible',
        'notes',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_responsible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }
}
