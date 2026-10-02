<?php

namespace App\Models;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use Database\Factories\FiscalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trabajo de impresión fiscal (factura, nota de crédito o reporte X/Z) en cola para el agente local.
 * El payload se congela al crearlo; el agente no recalcula montos.
 */
class FiscalDocument extends Model
{
    /** @use HasFactory<FiscalDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'fiscal_printer_id',
        'sale_id',
        'related_document_id',
        'type',
        'status',
        'simulation',
        'is_test',
        'payload',
        'expected_total_ves',
        'attempts',
        'printer_counter_before',
        'claimed_at',
        'started_at',
        'printed_at',
        'fiscal_number',
        'printer_serial',
        'z_number',
        'printer_datetime',
        'printer_total_ves',
        'error_code',
        'error_message',
        'raw_response',
        'requested_by',
        'resolved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FiscalDocumentType::class,
            'status' => FiscalDocumentStatus::class,
            'simulation' => 'boolean',
            'is_test' => 'boolean',
            'payload' => 'array',
            'raw_response' => 'array',
            'expected_total_ves' => 'decimal:2',
            'printer_total_ves' => 'decimal:2',
            'attempts' => 'integer',
            'claimed_at' => 'datetime',
            'started_at' => 'datetime',
            'printed_at' => 'datetime',
            'printer_datetime' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FiscalPrinter, $this>
     */
    public function fiscalPrinter(): BelongsTo
    {
        return $this->belongsTo(FiscalPrinter::class);
    }

    /**
     * Factura de prueba que anula esta nota de crédito de prueba (laboratorio fiscal).
     *
     * @return BelongsTo<FiscalDocument, $this>
     */
    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class, 'related_document_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
