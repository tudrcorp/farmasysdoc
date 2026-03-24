<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCompany extends Model
{
    use HasFactory;

    /**
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
}
