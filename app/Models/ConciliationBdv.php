<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliationBdv extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'user_id',
        'sale_id',
        'environment',
        'payer_document',
        'payer_phone',
        'destination_phone',
        'reference',
        'payment_date',
        'amount',
        'origin_bank',
        'req_ced',
        'bdv_http_status',
        'bdv_code',
        'bdv_message',
        'bdv_payload',
        'bdv_response',
        'conciliated_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'req_ced' => 'boolean',
            'bdv_http_status' => 'integer',
            'bdv_payload' => 'array',
            'bdv_response' => 'array',
            'payment_date' => 'date',
            'conciliated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
