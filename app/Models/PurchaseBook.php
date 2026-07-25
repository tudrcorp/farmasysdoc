<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBook extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'voucher_number',
        'retention_agent_name',
        'retention_agent_rif',
        'tax_period',
        'retention_agent_address',
        'issue_date',
        'supplier_name',
        'supplier_rif',
        'supplier_address',
        'operation_number',
        'invoice_date',
        'invoice_number',
        'invoice_control_number',
        'operation_class',
        'affected_control_number',
        'invoice_total_ves',
        'purchases_without_vat_credit',
        'taxable_base_ves',
        'vat_rate_percent',
        'tax_caused_ves',
        'tax_retained_ves',
        'bcv_rate_at_invoice',
        'seniat_retention_percent',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'voucher_number' => 'integer',
            'issue_date' => 'date',
            'operation_number' => 'integer',
            'invoice_date' => 'date',
            'operation_class' => 'integer',
            'invoice_total_ves' => 'decimal:2',
            'purchases_without_vat_credit' => 'decimal:2',
            'taxable_base_ves' => 'decimal:2',
            'vat_rate_percent' => 'decimal:2',
            'tax_caused_ves' => 'decimal:2',
            'tax_retained_ves' => 'decimal:2',
            'bcv_rate_at_invoice' => 'decimal:8',
            'seniat_retention_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
