<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalOfMovement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'partner_company_id',
        'total_quantity_products',
        'total_cost',
        'remaining_credit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_quantity_products' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'remaining_credit' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }
}
