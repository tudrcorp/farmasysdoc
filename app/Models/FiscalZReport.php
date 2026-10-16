<?php

namespace App\Models;

use App\Enums\FiscalZReportSource;
use Carbon\CarbonImmutable;
use Database\Factories\FiscalZReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Totales de un reporte Z leídos de la memoria fiscal de una máquina. Base del Libro de Ventas.
 * Es inmutable: lo que dice la memoria fiscal no se corrige en el sistema.
 */
class FiscalZReport extends Model
{
    /** @use HasFactory<FiscalZReportFactory> */
    use HasFactory;

    /**
     * Columnas de montos, en el orden en que se calcula el hash de integridad.
     *
     * @var list<string>
     */
    public const AMOUNT_COLUMNS = [
        'exempt_sales_ves', 'general_base_sales_ves', 'general_tax_sales_ves', 'reduced_base_sales_ves', 'reduced_tax_sales_ves',
        'additional_base_sales_ves', 'additional_tax_sales_ves', 'igtf_base_sales_ves', 'igtf_tax_sales_ves',
        'exempt_credit_notes_ves', 'general_base_credit_notes_ves', 'general_tax_credit_notes_ves', 'reduced_base_credit_notes_ves',
        'reduced_tax_credit_notes_ves', 'additional_base_credit_notes_ves', 'additional_tax_credit_notes_ves',
        'igtf_base_credit_notes_ves', 'igtf_tax_credit_notes_ves',
        'exempt_debit_notes_ves', 'general_base_debit_notes_ves', 'general_tax_debit_notes_ves', 'reduced_base_debit_notes_ves',
        'reduced_tax_debit_notes_ves', 'additional_base_debit_notes_ves', 'additional_tax_debit_notes_ves',
        'igtf_base_debit_notes_ves', 'igtf_tax_debit_notes_ves',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fiscal_printer_id',
        'branch_id',
        'fiscal_document_id',
        'printer_serial',
        'z_number',
        'report_date',
        'report_datetime',
        'last_invoice_number',
        'last_invoice_datetime',
        'last_credit_note_number',
        'last_debit_note_number',
        'last_non_fiscal_number',
        ...self::AMOUNT_COLUMNS,
        'general_rate_percent',
        'reduced_rate_percent',
        'additional_rate_percent',
        'igtf_rate_percent',
        'source',
        'content_hash',
        'raw',
        'captured_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Un reporte Z fiscal no se puede modificar.');
        });

        static::deleting(function (): void {
            throw new LogicException('Un reporte Z fiscal no se puede eliminar.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'z_number' => 'integer',
            'report_date' => 'date',
            'report_datetime' => 'datetime',
            'last_invoice_number' => 'integer',
            'last_invoice_datetime' => 'datetime',
            'last_credit_note_number' => 'integer',
            'last_debit_note_number' => 'integer',
            'last_non_fiscal_number' => 'integer',
            'general_rate_percent' => 'decimal:2',
            'reduced_rate_percent' => 'decimal:2',
            'additional_rate_percent' => 'decimal:2',
            'igtf_rate_percent' => 'decimal:2',
            'source' => FiscalZReportSource::class,
            'raw' => 'array',
            'captured_at' => 'datetime',
        ];

        foreach (self::AMOUNT_COLUMNS as $column) {
            $casts[$column] = 'decimal:2';
        }

        return $casts;
    }

    /**
     * Huella SHA-256 de los datos fiscales; permite demostrar que el registro no se alteró tras la captura.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function computeContentHash(array $attributes): string
    {
        $canonical = [
            'printer_serial' => (string) $attributes['printer_serial'],
            'z_number' => (int) $attributes['z_number'],
            'report_date' => (string) $attributes['report_date'],
            'last_invoice_number' => (int) $attributes['last_invoice_number'],
            'last_credit_note_number' => (int) ($attributes['last_credit_note_number'] ?? 0),
            'last_debit_note_number' => (int) ($attributes['last_debit_note_number'] ?? 0),
        ];

        foreach (self::AMOUNT_COLUMNS as $column) {
            $canonical[$column] = number_format((float) ($attributes[$column] ?? 0), 2, '.', '');
        }

        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    public function hasValidContentHash(): bool
    {
        $attributes = $this->only(['printer_serial', 'z_number', 'last_invoice_number', 'last_credit_note_number', 'last_debit_note_number', ...self::AMOUNT_COLUMNS]);
        $attributes['report_date'] = $this->report_date?->toDateString();

        return hash_equals((string) $this->content_hash, self::computeContentHash($attributes));
    }

    /**
     * Fecha de las operaciones del Z (la que va al Libro de Ventas): la de su última factura.
     * Un Z impreso a la mañana siguiente cubre las ventas del día anterior; el IVA se causa con la factura.
     * Si el Z no tiene facturas propias (numeración igual al Z anterior) se usa la fecha de emisión del Z.
     */
    public function operationsDate(?self $previous = null): CarbonImmutable
    {
        $emitted = CarbonImmutable::parse($this->report_datetime ?? $this->report_date);
        $hasInvoices = ! $previous instanceof self || $this->last_invoice_number > $previous->last_invoice_number;

        if ($hasInvoices && $this->last_invoice_datetime !== null) {
            $lastInvoice = CarbonImmutable::parse($this->last_invoice_datetime);

            if ($lastInvoice->lessThanOrEqualTo($emitted->endOfDay())) {
                return $lastInvoice->startOfDay();
            }
        }

        return CarbonImmutable::parse($this->report_date)->startOfDay();
    }

    public function previousReport(): ?self
    {
        return self::query()
            ->where('printer_serial', $this->printer_serial)
            ->where('z_number', $this->z_number - 1)
            ->first();
    }

    public function salesTotalVes(): float
    {
        return round(
            (float) $this->exempt_sales_ves
            + (float) $this->general_base_sales_ves + (float) $this->general_tax_sales_ves
            + (float) $this->reduced_base_sales_ves + (float) $this->reduced_tax_sales_ves
            + (float) $this->additional_base_sales_ves + (float) $this->additional_tax_sales_ves,
            2,
        );
    }

    /**
     * @return BelongsTo<FiscalPrinter, $this>
     */
    public function fiscalPrinter(): BelongsTo
    {
        return $this->belongsTo(FiscalPrinter::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<FiscalDocument, $this>
     */
    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
