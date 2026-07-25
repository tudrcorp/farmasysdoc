<?php

namespace App\Models;

use App\Enums\PurchaseLedgerDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLedger extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'purchase_book_id',
        'tax_period',
        'operation_number',
        'document_type',
        'document_number',
        'control_number',
        'supplier_name',
        'supplier_tax_id',
        'taxpayer_type',
        'total_with_vat_and_exempt_ves',
        'exempt_amount_ves',
        'export_amount_ves',
        'taxable_base_ves',
        'tax_caused_ves',
        'taxable_base_reduced_ves',
        'tax_reduced_ves',
        'vat_rate_percent',
        'retention_voucher_issued_at',
        'retention_voucher_number',
        'retention_amount_ves',
        'invoice_date',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => PurchaseLedgerDocumentType::class,
            'operation_number' => 'integer',
            'taxpayer_type' => 'string',
            'total_with_vat_and_exempt_ves' => 'decimal:2',
            'exempt_amount_ves' => 'decimal:2',
            'export_amount_ves' => 'decimal:2',
            'taxable_base_ves' => 'decimal:2',
            'tax_caused_ves' => 'decimal:2',
            'taxable_base_reduced_ves' => 'decimal:2',
            'tax_reduced_ves' => 'decimal:2',
            'vat_rate_percent' => 'decimal:2',
            'retention_voucher_issued_at' => 'date',
            'retention_voucher_number' => 'integer',
            'retention_amount_ves' => 'decimal:2',
            'invoice_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<PurchaseBook, $this>
     */
    public function purchaseBook(): BelongsTo
    {
        return $this->belongsTo(PurchaseBook::class);
    }
}
