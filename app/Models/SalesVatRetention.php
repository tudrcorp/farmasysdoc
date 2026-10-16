<?php

namespace App\Models;

use App\Services\Fiscal\SalesBookPeriodLock;
use Database\Factories\SalesVatRetentionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante de retención de IVA que un cliente (agente de retención) entregó por una factura nuestra.
 * Se declara en la quincena de su recepción; no se puede tocar si esa quincena ya está cerrada.
 */
class SalesVatRetention extends Model
{
    /** @use HasFactory<SalesVatRetentionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'fiscal_printer_id',
        'customer_name',
        'customer_rif',
        'voucher_number',
        'voucher_issued_on',
        'received_on',
        'invoice_number',
        'invoice_date',
        'invoice_total_ves',
        'invoice_tax_ves',
        'retention_percent',
        'retained_amount_ves',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (SalesVatRetention $retention): void {
            $lock = app(SalesBookPeriodLock::class);

            if ($retention->exists) {
                $lock->assertOpen((int) $retention->getOriginal('branch_id'), $retention->getRawOriginal('received_on'));
            }

            $lock->assertOpen((int) $retention->branch_id, $retention->received_on);
        });

        static::deleting(function (SalesVatRetention $retention): void {
            app(SalesBookPeriodLock::class)->assertOpen((int) $retention->branch_id, $retention->received_on);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'voucher_issued_on' => 'date',
            'received_on' => 'date',
            'invoice_date' => 'date',
            'invoice_total_ves' => 'decimal:2',
            'invoice_tax_ves' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'retained_amount_ves' => 'decimal:2',
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
     * @return BelongsTo<FiscalPrinter, $this>
     */
    public function fiscalPrinter(): BelongsTo
    {
        return $this->belongsTo(FiscalPrinter::class);
    }
}
