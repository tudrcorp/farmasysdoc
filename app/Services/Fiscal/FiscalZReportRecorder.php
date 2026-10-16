<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentType;
use App\Enums\FiscalZReportSource;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Services\Audit\AuditLogger;
use App\Support\Fiscal\SalesBookPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Guarda los reportes Z que el agente leyó de la memoria fiscal.
 *
 * - Solo acepta datos de la máquina cuyo registro fiscal coincide con la configurada.
 * - Es idempotente: el mismo Z con los mismos montos se ignora.
 * - Nunca sobrescribe: un Z repetido con montos distintos queda en auditoría para revisión.
 */
final class FiscalZReportRecorder
{
    /**
     * Campos del agente (sin sufijo) que se guardan con sufijo «_ves».
     *
     * @var list<string>
     */
    private const AGENT_AMOUNT_FIELDS = [
        'exempt_sales', 'general_base_sales', 'general_tax_sales', 'reduced_base_sales', 'reduced_tax_sales',
        'additional_base_sales', 'additional_tax_sales', 'igtf_base_sales', 'igtf_tax_sales',
        'exempt_credit_notes', 'general_base_credit_notes', 'general_tax_credit_notes', 'reduced_base_credit_notes',
        'reduced_tax_credit_notes', 'additional_base_credit_notes', 'additional_tax_credit_notes',
        'igtf_base_credit_notes', 'igtf_tax_credit_notes',
        'exempt_debit_notes', 'general_base_debit_notes', 'general_tax_debit_notes', 'reduced_base_debit_notes',
        'reduced_tax_debit_notes', 'additional_base_debit_notes', 'additional_tax_debit_notes',
        'igtf_base_debit_notes', 'igtf_tax_debit_notes',
    ];

    public function __construct(private SalesBookPeriodLock $lock) {}

    public static function normalizeSerial(?string $serial): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $serial));
    }

    /**
     * Mismo criterio que el agente: el registro impreso puede venir sin el sufijo de la etiqueta (p. ej. «-I»).
     */
    public static function serialMatchesPrinter(string $serial, FiscalPrinter $printer): bool
    {
        $actual = self::normalizeSerial($serial);
        $expected = self::normalizeSerial($printer->fiscal_registry);

        return $actual !== '' && $expected !== ''
            && ($actual === $expected || str_starts_with($expected, $actual) || str_starts_with($actual, $expected));
    }

    /**
     * @param  list<array<string, mixed>>  $reports
     * @param  array<string, mixed>  $raw
     * @return array{created: int, duplicates: int, conflicts: list<int>, rejected: list<string>}
     */
    public function recordFromAgent(FiscalDocument $document, array $reports, ?string $printerSerial, array $raw = []): array
    {
        $summary = ['created' => 0, 'duplicates' => 0, 'conflicts' => [], 'rejected' => []];
        $printer = $document->fiscalPrinter;

        if (! $printer instanceof FiscalPrinter || $reports === []) {
            return $summary;
        }

        $serial = self::normalizeSerial($printerSerial);

        if (! self::serialMatchesPrinter($serial, $printer)) {
            $summary['rejected'][] = 'serial';

            AuditLogger::record(
                'fiscal_z_report_serial_mismatch',
                'Fiscal · Reportes Z rechazados: la máquina leída ('.($serial ?: 'sin registro').') no es '.$printer->fiscal_registry,
                FiscalDocument::class,
                $document->id,
                $document->uuid,
                ['module' => 'fiscal', 'fiscal_printer_id' => $printer->id, 'serial' => $serial],
            );

            return $summary;
        }

        $source = $document->type === FiscalDocumentType::ZReport ? FiscalZReportSource::AfterPrint : FiscalZReportSource::MemoryRead;
        $rates = $this->taxRates($raw);

        foreach ($reports as $report) {
            $attributes = $this->attributes($report, $serial);

            if ($attributes === null) {
                $summary['rejected'][] = 'invalid:'.json_encode($report['z_number'] ?? null);

                continue;
            }

            $outcome = $this->store($printer, $document, $attributes, $source, $rates, $report);

            match ($outcome) {
                'created' => $summary['created']++,
                'duplicate' => $summary['duplicates']++,
                default => $summary['conflicts'][] = $attributes['z_number'],
            };
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{general: ?float, reduced: ?float, additional: ?float, igtf: ?float}  $rates
     * @param  array<string, mixed>  $report
     */
    private function store(FiscalPrinter $printer, FiscalDocument $document, array $attributes, FiscalZReportSource $source, array $rates, array $report): string
    {
        $hash = FiscalZReport::computeContentHash($attributes);

        $result = DB::transaction(function () use ($printer, $document, $attributes, $source, $rates, $report, $hash): array {
            $existing = FiscalZReport::query()
                ->where('printer_serial', $attributes['printer_serial'])
                ->where('z_number', $attributes['z_number'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof FiscalZReport) {
                return [hash_equals($existing->content_hash, $hash) ? 'duplicate' : 'conflict', $existing];
            }

            $created = FiscalZReport::query()->create(array_merge($attributes, [
                'fiscal_printer_id' => $printer->id,
                'branch_id' => $printer->branch_id,
                'fiscal_document_id' => $document->id,
                'general_rate_percent' => $rates['general'],
                'reduced_rate_percent' => $rates['reduced'],
                'additional_rate_percent' => $rates['additional'],
                'igtf_rate_percent' => $rates['igtf'],
                'source' => $source,
                'content_hash' => $hash,
                'raw' => $report,
                'captured_at' => now(),
            ]));

            return ['created', $created];
        });

        [$outcome, $zReport] = $result;

        if ($outcome === 'conflict') {
            AuditLogger::record(
                'fiscal_z_report_conflict',
                'Fiscal · Z Nº '.$attributes['z_number'].' de '.$attributes['printer_serial'].' leído con montos distintos a los ya guardados (no se sobrescribió)',
                FiscalZReport::class,
                $zReport->id,
                'Z '.$attributes['z_number'],
                ['module' => 'fiscal', 'fiscal_document_id' => $document->id, 'received' => $report],
            );
        }

        if ($outcome === 'created') {
            AuditLogger::record(
                'fiscal_z_report_captured',
                'Fiscal · Z Nº '.$zReport->z_number.' capturado · '.$printer->name,
                FiscalZReport::class,
                $zReport->id,
                'Z '.$zReport->z_number,
                ['module' => 'fiscal', 'fiscal_document_id' => $document->id, 'source' => $source->value],
            );

            if ($this->lock->isClosed((int) $printer->branch_id, SalesBookPeriod::containing($zReport->report_date))) {
                AuditLogger::record(
                    'fiscal_z_report_after_close',
                    'Fiscal · Z Nº '.$zReport->z_number.' capturado en una quincena con Libro de Ventas cerrado; debe reabrirse',
                    FiscalZReport::class,
                    $zReport->id,
                    'Z '.$zReport->z_number,
                    ['module' => 'fiscal', 'branch_id' => $printer->branch_id],
                );
            }
        }

        return $outcome;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>|null
     */
    private function attributes(array $report, string $serial): ?array
    {
        $zNumber = filter_var($report['z_number'] ?? null, FILTER_VALIDATE_INT);
        $lastInvoice = filter_var($report['last_invoice_number'] ?? null, FILTER_VALIDATE_INT);
        $datetime = $this->parseDate($report['z_datetime'] ?? null);

        if ($zNumber === false || $zNumber <= 0 || $lastInvoice === false || $lastInvoice < 0 || $datetime === null) {
            return null;
        }

        $attributes = [
            'printer_serial' => $serial,
            'z_number' => $zNumber,
            'report_date' => $datetime->toDateString(),
            'report_datetime' => $datetime,
            'last_invoice_number' => $lastInvoice,
            'last_invoice_datetime' => $this->parseDate($report['last_invoice_datetime'] ?? null),
            'last_credit_note_number' => max(0, (int) ($report['last_credit_note_number'] ?? 0)),
            'last_debit_note_number' => max(0, (int) ($report['last_debit_note_number'] ?? 0)),
            'last_non_fiscal_number' => isset($report['last_non_fiscal_number']) ? max(0, (int) $report['last_non_fiscal_number']) : null,
        ];

        foreach (self::AGENT_AMOUNT_FIELDS as $field) {
            $value = $report[$field] ?? 0;

            if (! is_numeric($value)) {
                return null;
            }

            $attributes[$field.'_ves'] = round((float) $value, 2);
        }

        return $attributes;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        return $date->year >= 2000 ? $date : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{general: ?float, reduced: ?float, additional: ?float, igtf: ?float}
     */
    private function taxRates(array $raw): array
    {
        $rates = is_array($raw['tax_rates'] ?? null) ? array_values($raw['tax_rates']) : [];
        $pick = fn (mixed $value): ?float => is_numeric($value) ? round((float) $value, 2) : null;

        return [
            'general' => $pick($rates[0] ?? null),
            'reduced' => $pick($rates[1] ?? null),
            'additional' => $pick($rates[2] ?? null),
            'igtf' => $pick($raw['igtf_rate'] ?? null),
        ];
    }
}
