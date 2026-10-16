<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\Branch;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Models\SalesVatRetention;
use App\Support\Finance\DefaultIgtfRate;
use App\Support\Finance\DefaultVatRate;
use App\Support\Fiscal\SalesBookPeriod;
use App\Support\Fiscal\VenezuelanRifFormatter;
use App\Support\Fiscal\VenezuelanTaxpayerClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma el Libro de Ventas de una sucursal y quincena a partir de los reportes Z leídos de la memoria fiscal.
 *
 * Reglas:
 * - Una línea de resumen por reporte Z con las ventas a no contribuyentes (Z − facturas a contribuyentes).
 * - Las facturas y notas de crédito a contribuyentes se detallan una por una.
 * - Las notas de crédito/débito del Z van en líneas propias, con signo negativo las de crédito.
 * - Los comprobantes de retención de IVA se declaran en la quincena de su recepción.
 * - Los montos se suman en céntimos (enteros) para no acumular errores de redondeo.
 * - Los totales del libro cuadran exactamente con la suma de los reportes Z.
 */
final class SalesBookBuilder
{
    public const LEVEL_ERROR = 'error';

    public const LEVEL_WARNING = 'warning';

    /**
     * Columnas de montos de cada línea (en céntimos durante el cálculo).
     *
     * @var list<string>
     */
    public const AMOUNT_KEYS = [
        'total', 'exempt', 'export', 'contributor_base', 'contributor_tax',
        'non_contributor_base', 'non_contributor_tax', 'igtf_base', 'igtf_tax', 'retained',
    ];

    private const BUCKETS = ['general', 'reduced', 'additional'];

    /**
     * @var list<array{level: string, message: string}>
     */
    private array $issues = [];

    /**
     * @return array{
     *     branch_id: int,
     *     period_key: string,
     *     header: array<string, string|int>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, float>,
     *     summary: array<string, float>,
     *     printers: list<array<string, mixed>>,
     *     issues: list<array{level: string, message: string}>,
     *     z_reports_count: int,
     *     generated_at: string,
     *     generated_by: string
     * }
     */
    public function build(Branch $branch, SalesBookPeriod $period, ?string $generatedBy = null): array
    {
        $this->issues = [];

        $zReports = FiscalZReport::query()
            ->with('fiscalPrinter')
            ->where('branch_id', $branch->id)
            ->whereBetween('report_date', [$period->start()->toDateString(), $period->end()->toDateString()])
            ->orderBy('report_date')
            ->orderBy('printer_serial')
            ->orderBy('z_number')
            ->get();

        $previousBySerial = $this->previousZReports($zReports);
        $vatRate = (float) DefaultVatRate::percent();

        $rows = [];
        $printers = [];

        foreach ($zReports->groupBy('printer_serial') as $serial => $serialReports) {
            $printers[] = $this->checkContinuity((string) $serial, $serialReports, $previousBySerial, $period);
        }

        $this->checkPrintersWithoutReports($branch, $zReports, $period);
        $this->checkPendingFiscalWork($branch, $period);

        $contributorDocs = $this->contributorDocuments($zReports, $period);

        foreach ($zReports as $zReport) {
            if (! $zReport->hasValidContentHash()) {
                $this->issue(self::LEVEL_ERROR, 'El Z Nº '.$this->pad($zReport->z_number, 4).' de '.$zReport->printer_serial.' no supera la verificación de integridad (los datos fueron alterados).');
            }

            $previous = $previousBySerial[$zReport->printer_serial][$zReport->z_number - 1] ?? null;
            $rate = $zReport->general_rate_percent !== null ? (float) $zReport->general_rate_percent : $vatRate;

            foreach ($this->rowsForZReport($zReport, $previous, $contributorDocs, $rate) as $row) {
                $rows[] = $row;
            }
        }

        $rows = $this->attachRetentions($rows, $branch, $period);

        usort($rows, fn (array $a, array $b): int => [$a['sort_date'], $a['sort_serial'], $a['sort_z'], $a['sort_order'], $a['sort_number']]
            <=> [$b['sort_date'], $b['sort_serial'], $b['sort_z'], $b['sort_order'], $b['sort_number']]);

        $totals = array_fill_keys(self::AMOUNT_KEYS, 0);
        $summaryCents = [
            'exempt' => 0,
            'export' => 0,
            'general_base' => 0, 'general_tax' => 0,
            'reduced_base' => 0, 'reduced_tax' => 0,
            'additional_base' => 0, 'additional_tax' => 0,
            'retained' => 0,
        ];

        foreach ($rows as $index => $row) {
            $rows[$index]['operation_number'] = $index + 1;

            foreach (self::AMOUNT_KEYS as $key) {
                $totals[$key] += $row['amounts'][$key];
            }

            $summaryCents['exempt'] += $row['amounts']['exempt'];
            $summaryCents['export'] += $row['amounts']['export'];
            $summaryCents[$row['bucket'].'_base'] += $row['amounts']['contributor_base'] + $row['amounts']['non_contributor_base'];
            $summaryCents[$row['bucket'].'_tax'] += $row['amounts']['contributor_tax'] + $row['amounts']['non_contributor_tax'];
            $summaryCents['retained'] += $row['amounts']['retained'];
        }

        $this->checkTotalsAgainstZ($zReports, $totals);

        if (! $period->hasEnded()) {
            $this->issue(self::LEVEL_ERROR, 'La quincena todavía no termina (cierra el '.$period->end()->format('d/m/Y').'); el libro es provisional.');
        }

        if ($zReports->isEmpty()) {
            $this->issue(self::LEVEL_WARNING, 'No hay reportes Z capturados para esta sucursal en la quincena.');
        }

        return [
            'branch_id' => (int) $branch->id,
            'period_key' => $period->key(),
            'header' => $this->header($branch, $period),
            'rows' => array_map(fn (array $row): array => $this->presentRow($row), $rows),
            'totals' => array_map(fn (int $cents): float => $this->money($cents), $totals),
            'summary' => $this->summary($summaryCents),
            'printers' => $printers,
            'issues' => $this->issues,
            'z_reports_count' => $zReports->count(),
            'generated_at' => now()->format('d/m/Y h:i A'),
            'generated_by' => (string) ($generatedBy ?? 'sistema'),
        ];
    }

    /**
     * @param  array{issues: list<array{level: string, message: string}>}  $book
     */
    public static function hasBlockingIssues(array $book): bool
    {
        foreach ($book['issues'] as $issue) {
            if ($issue['level'] === self::LEVEL_ERROR) {
                return true;
            }
        }

        return false;
    }

    /**
     * Z inmediatamente anteriores (y los del período) por máquina, para conocer el primer documento de cada Z.
     *
     * @param  Collection<int, FiscalZReport>  $zReports
     * @return array<string, array<int, FiscalZReport>>
     */
    private function previousZReports(Collection $zReports): array
    {
        $map = [];

        foreach ($zReports->groupBy('printer_serial') as $serial => $serialReports) {
            $numbers = $serialReports->pluck('z_number')->map(fn (int $n): int => $n - 1)->all();

            $map[$serial] = FiscalZReport::query()
                ->where('printer_serial', $serial)
                ->whereIn('z_number', $numbers)
                ->get()
                ->keyBy('z_number')
                ->all();
        }

        return $map;
    }

    /**
     * @param  Collection<int, FiscalZReport>  $serialReports
     * @param  array<string, array<int, FiscalZReport>>  $previousBySerial
     * @return array<string, mixed>
     */
    private function checkContinuity(string $serial, Collection $serialReports, array $previousBySerial, SalesBookPeriod $period): array
    {
        $numbers = $serialReports->pluck('z_number')->sort()->values();
        $first = (int) $numbers->first();
        $last = (int) $numbers->last();
        $printer = $serialReports->first()?->fiscalPrinter;
        $label = ($printer?->name ? $printer->name.' · ' : '').$serial;

        $missing = array_values(array_diff(range($first, $last), $numbers->all()));

        if ($missing !== []) {
            $this->issue(self::LEVEL_ERROR, $label.': faltan los reportes Z '.implode(', ', array_map(fn (int $n): string => $this->pad($n, 4), $missing)).'.');
        }

        if ($first > 1 && ! isset($previousBySerial[$serial][$first - 1])) {
            $hasOlder = FiscalZReport::query()->where('printer_serial', $serial)->where('z_number', '<', $first)->exists();

            $this->issue(
                $hasOlder ? self::LEVEL_ERROR : self::LEVEL_WARNING,
                $hasOlder
                    ? $label.': falta el Z '.$this->pad($first - 1, 4).' (anterior al primero de la quincena).'
                    : $label.': el Z '.$this->pad($first, 4).' es el primero capturado de esta máquina; su ticket inicial no se puede determinar.',
            );
        }

        $hasNext = FiscalZReport::query()->where('printer_serial', $serial)->where('z_number', $last + 1)->exists();
        $printerLast = filter_var($printer?->last_z_number, FILTER_VALIDATE_INT);

        if (! $hasNext && $printerLast !== false && $printerLast > $last) {
            $this->issue(self::LEVEL_ERROR, $label.': la máquina ya emitió el Z '.$this->pad($printerLast, 4).' y faltan por leer los posteriores al '.$this->pad($last, 4).'. Use «Leer reportes Z».');
        }

        $days = $serialReports->map(fn (FiscalZReport $z): string => $z->report_date->toDateString());

        foreach ($days->duplicates() as $day) {
            $this->issue(self::LEVEL_WARNING, $label.': hay más de un Z el '.Carbon::parse($day)->format('d/m/Y').' (revise si fue un cierre adicional).');
        }

        return [
            'serial' => $serial,
            'name' => (string) ($printer?->name ?? $serial),
            'first_z' => $first,
            'last_z' => $last,
            'count' => $numbers->count(),
            'missing' => $missing,
        ];
    }

    /**
     * @param  Collection<int, FiscalZReport>  $zReports
     */
    private function checkPrintersWithoutReports(Branch $branch, Collection $zReports, SalesBookPeriod $period): void
    {
        $withReports = $zReports->pluck('fiscal_printer_id')->unique()->all();

        FiscalPrinter::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereNotIn('id', $withReports)
            ->whereDate('created_at', '<=', $period->end()->toDateString())
            ->get()
            ->each(fn (FiscalPrinter $printer) => $this->issue(
                self::LEVEL_WARNING,
                $printer->name.' ('.$printer->fiscal_registry.') no tiene reportes Z capturados en la quincena.',
            ));
    }

    private function checkPendingFiscalWork(Branch $branch, SalesBookPeriod $period): void
    {
        $base = FiscalDocument::query()
            ->whereHas('fiscalPrinter', fn ($query) => $query->where('branch_id', $branch->id))
            ->where('simulation', false)
            ->where('is_test', false);

        $pendingZ = (clone $base)
            ->whereIn('type', [FiscalDocumentType::ZReport, FiscalDocumentType::ZReading])
            ->whereIn('status', [FiscalDocumentStatus::Pending, ...FiscalDocumentStatus::inFlight()])
            ->count();

        if ($pendingZ > 0) {
            $this->issue(self::LEVEL_ERROR, 'Hay '.$pendingZ.' reporte(s)/lectura(s) Z en cola sin terminar en las máquinas de la sucursal.');
        }

        $toReview = (clone $base)
            ->whereIn('type', [FiscalDocumentType::Invoice, FiscalDocumentType::CreditNote, FiscalDocumentType::ZReport, FiscalDocumentType::ZReading])
            ->where('status', FiscalDocumentStatus::NeedsReview)
            ->whereBetween('created_at', [$period->start()->subDays(2)->startOfDay(), $period->end()->addDays(2)->endOfDay()])
            ->count();

        if ($toReview > 0) {
            $this->issue(self::LEVEL_ERROR, 'Hay '.$toReview.' documento(s) fiscal(es) de la quincena en «Requiere revisión»; resuélvalos antes de cerrar.');
        }
    }

    /**
     * Facturas y notas de crédito a contribuyentes impresas por las máquinas de los Z, indexadas por máquina y tipo.
     *
     * @param  Collection<int, FiscalZReport>  $zReports
     * @return array<int, array<string, list<array<string, mixed>>>>
     */
    private function contributorDocuments(Collection $zReports, SalesBookPeriod $period): array
    {
        $printerIds = $zReports->pluck('fiscal_printer_id')->unique()->values()->all();

        if ($printerIds === []) {
            return [];
        }

        $documents = [];

        FiscalDocument::query()
            ->whereIn('fiscal_printer_id', $printerIds)
            ->whereIn('type', [FiscalDocumentType::Invoice, FiscalDocumentType::CreditNote])
            ->where('status', FiscalDocumentStatus::Printed)
            ->where('simulation', false)
            ->where('is_test', false)
            ->whereNotNull('fiscal_number')
            ->whereBetween('created_at', [$period->start()->subDays(10)->startOfDay(), $period->end()->addDays(3)->endOfDay()])
            ->orderBy('id')
            ->lazy(500)
            ->each(function (FiscalDocument $document) use (&$documents): void {
                $payload = is_array($document->payload) ? $document->payload : [];
                $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];

                if (! VenezuelanTaxpayerClassifier::isTaxpayer($customer['document'] ?? null)) {
                    return;
                }

                $documents[$document->fiscal_printer_id][$document->type->value][] = [
                    'document' => $document,
                    'number' => (int) preg_replace('/\D/', '', (string) $document->fiscal_number),
                    'customer_name' => (string) ($customer['name'] ?? ''),
                    'customer_rif' => VenezuelanRifFormatter::format((string) ($customer['document'] ?? '')),
                    'payload' => $payload,
                ];
            });

        return $documents;
    }

    /**
     * @param  array<int, array<string, list<array<string, mixed>>>>  $contributorDocs
     * @return list<array<string, mixed>>
     */
    private function rowsForZReport(FiscalZReport $zReport, ?FiscalZReport $previous, array $contributorDocs, float $rate): array
    {
        $rows = [];
        $zLabel = 'REPORTE FISCAL Z NRO '.$this->pad($zReport->z_number, 4);
        $firstInvoice = $previous instanceof FiscalZReport ? $previous->last_invoice_number + 1 : null;
        $firstCreditNote = $previous instanceof FiscalZReport ? $previous->last_credit_note_number + 1 : null;

        $invoices = $this->documentsInRange(
            $contributorDocs[$zReport->fiscal_printer_id][FiscalDocumentType::Invoice->value] ?? [],
            $firstInvoice,
            $zReport->last_invoice_number,
            $zReport,
        );
        $creditNotes = $this->documentsInRange(
            $contributorDocs[$zReport->fiscal_printer_id][FiscalDocumentType::CreditNote->value] ?? [],
            $firstCreditNote,
            $zReport->last_credit_note_number,
            $zReport,
        );

        $context = [
            'z' => $zReport,
            'date' => $zReport->report_date->toDateString(),
            'serial' => $zReport->printer_serial,
        ];

        $sales = $this->zBuckets($zReport, 'sales');
        $invoiceRows = [];

        foreach ($invoices as $invoice) {
            $amounts = $this->documentBuckets($invoice, $rate, $zReport);
            $sales = $this->subtractBuckets($sales, $amounts);
            $invoiceRows[] = $this->documentRow($context, $invoice, $amounts, 'FACTURA', 1, 2);
        }

        $this->assertNonNegative($sales, $zLabel.' (ventas)');

        $hasInvoices = $firstInvoice === null || $firstInvoice <= $zReport->last_invoice_number;
        $rows = array_merge($rows, $this->summaryRows($context, $sales, [
            'document_type' => 'REPORTE Z',
            'first_document' => $firstInvoice !== null && $hasInvoices ? $this->pad($firstInvoice, 8) : null,
            'last_document' => $hasInvoices ? $this->pad($zReport->last_invoice_number, 8) : null,
            'customer_name' => $zLabel.($hasInvoices ? '' : ' (SIN VENTAS)'),
            'sort_order' => 0,
        ], 1, forceRow: true));
        $rows = array_merge($rows, $invoiceRows);

        $credits = $this->zBuckets($zReport, 'credit_notes');
        $creditRows = [];

        foreach ($creditNotes as $creditNote) {
            $amounts = $this->documentBuckets($creditNote, $rate, $zReport);
            $credits = $this->subtractBuckets($credits, $amounts);
            $creditRows[] = $this->documentRow($context, $creditNote, $amounts, 'NOTA DE CRÉDITO', -1, 4);
        }

        $this->assertNonNegative($credits, $zLabel.' (notas de crédito)');

        if ($this->bucketsTotal($credits) !== 0) {
            $rows = array_merge($rows, $this->summaryRows($context, $credits, [
                'document_type' => 'NOTA DE CRÉDITO',
                'first_document' => $firstCreditNote !== null && $firstCreditNote <= $zReport->last_credit_note_number ? $this->pad($firstCreditNote, 8) : null,
                'last_document' => $this->pad($zReport->last_credit_note_number, 8),
                'customer_name' => 'NOTAS DE CRÉDITO '.$zLabel,
                'sort_order' => 3,
            ], -1));
        }

        $rows = array_merge($rows, $creditRows);

        $debits = $this->zBuckets($zReport, 'debit_notes');
        $rows = array_merge($rows, $this->summaryRows($context, $debits, [
            'document_type' => 'NOTA DE DÉBITO',
            'first_document' => null,
            'last_document' => $this->pad($zReport->last_debit_note_number, 8),
            'customer_name' => 'NOTAS DE DÉBITO '.$zLabel,
            'sort_order' => 5,
        ], 1));

        return $rows;
    }

    /**
     * Documentos que entran en un Z: su número está entre el último del Z anterior (+1) y el último de este Z.
     * Sin Z anterior capturado (primer Z de la máquina) se usan los del mismo día con número hasta el último del Z.
     *
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    private function documentsInRange(array $documents, ?int $first, int $last, FiscalZReport $zReport): array
    {
        $day = $zReport->report_date->toDateString();

        return array_values(array_filter($documents, function (array $doc) use ($first, $last, $day): bool {
            if ($doc['number'] <= 0 || $doc['number'] > $last) {
                return false;
            }

            if ($first !== null) {
                return $doc['number'] >= $first;
            }

            /** @var FiscalDocument $document */
            $document = $doc['document'];

            return ($document->printer_datetime ?? $document->printed_at)?->toDateString() === $day;
        }));
    }

    /**
     * Montos por alícuota del Z (céntimos).
     *
     * @return array{exempt: int, general: array{0: int, 1: int}, reduced: array{0: int, 1: int}, additional: array{0: int, 1: int}, igtf: array{0: int, 1: int}}
     */
    private function zBuckets(FiscalZReport $zReport, string $group): array
    {
        $cents = fn (string $column): int => $this->cents($zReport->{$column});

        return [
            'exempt' => $cents("exempt_{$group}_ves"),
            'general' => [$cents("general_base_{$group}_ves"), $cents("general_tax_{$group}_ves")],
            'reduced' => [$cents("reduced_base_{$group}_ves"), $cents("reduced_tax_{$group}_ves")],
            'additional' => [$cents("additional_base_{$group}_ves"), $cents("additional_tax_{$group}_ves")],
            'igtf' => [$cents("igtf_base_{$group}_ves"), $cents("igtf_tax_{$group}_ves")],
        ];
    }

    /**
     * Montos de una factura/NC a contribuyente según su payload congelado (lo mismo que se envió a la máquina).
     *
     * @param  array<string, mixed>  $doc
     * @return array{exempt: int, general: array{0: int, 1: int}, reduced: array{0: int, 1: int}, additional: array{0: int, 1: int}, igtf: array{0: int, 1: int}}
     */
    private function documentBuckets(array $doc, float $rate, FiscalZReport $zReport): array
    {
        $exempt = 0;
        $base = 0;

        foreach ((array) ($doc['payload']['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $line = (int) round(((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price_ves'] ?? 0) - (float) ($item['discount_ves'] ?? 0)) * 100);

            if (($item['tax_code'] ?? 'E') === FiscalDocumentPayloadBuilder::TAX_CODE_GENERAL && (float) ($item['tax_rate_percent'] ?? 0) > 0) {
                $base += $line;
            } else {
                $exempt += $line;
            }
        }

        $tax = (int) round($base * $rate / 100);
        $buckets = [
            'exempt' => $exempt,
            'general' => [$base, $tax],
            'reduced' => [0, 0],
            'additional' => [0, 0],
            'igtf' => [0, 0],
        ];

        /** @var FiscalDocument $document */
        $document = $doc['document'];

        if ($document->printer_total_ves !== null) {
            $printerTotal = $this->cents($document->printer_total_ves);
            $computed = $exempt + $base + $tax;
            $igtf = $this->cents($doc['payload']['expected']['igtf_ves'] ?? 0);
            $tolerance = $this->cents(config('fiscal.printers.total_tolerance_ves', 0.05));

            if (abs($printerTotal - $computed) > $tolerance && abs($printerTotal - $computed - $igtf) > $tolerance) {
                $this->issue(self::LEVEL_WARNING, 'Documento '.$document->fiscal_number.' (Z '.$this->pad($zReport->z_number, 4).'): el total calculado Bs '
                    .$this->format($computed).' difiere del impreso Bs '.$this->format($printerTotal).'. Verifique contra la copia del ticket.');
            }
        }

        return $buckets;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function subtractBuckets(array $a, array $b): array
    {
        $a['exempt'] -= $b['exempt'];

        foreach ([...self::BUCKETS, 'igtf'] as $bucket) {
            $a[$bucket] = [$a[$bucket][0] - $b[$bucket][0], $a[$bucket][1] - $b[$bucket][1]];
        }

        return $a;
    }

    /**
     * @param  array<string, mixed>  $buckets
     */
    private function bucketsTotal(array $buckets): int
    {
        $total = abs($buckets['exempt']);

        foreach (self::BUCKETS as $bucket) {
            $total += abs($buckets[$bucket][0]) + abs($buckets[$bucket][1]);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $buckets
     */
    private function assertNonNegative(array $buckets, string $label): void
    {
        $negative = $buckets['exempt'] < 0;

        foreach (self::BUCKETS as $bucket) {
            $negative = $negative || $buckets[$bucket][0] < 0 || $buckets[$bucket][1] < 0;
        }

        if ($negative) {
            $this->issue(self::LEVEL_ERROR, $label.': las facturas a contribuyentes suman más que el reporte Z. Revise los documentos fiscales de ese Z.');
        }
    }

    /**
     * Línea(s) de resumen de un Z para no contribuyentes: una por alícuota con montos (la general siempre, aunque esté en cero).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $buckets
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private function summaryRows(array $context, array $buckets, array $meta, int $sign, bool $forceRow = false): array
    {
        /** @var FiscalZReport $zReport */
        $zReport = $context['z'];
        $rows = [];

        foreach (self::BUCKETS as $index => $bucket) {
            [$base, $tax] = $buckets[$bucket];
            $exempt = $bucket === 'general' ? $buckets['exempt'] : 0;

            if ($base === 0 && $tax === 0 && $exempt === 0 && ! ($bucket === 'general' && $forceRow)) {
                continue;
            }

            $amounts = $this->emptyAmounts();
            $amounts['exempt'] = $sign * $exempt;
            $amounts['non_contributor_base'] = $sign * $base;
            $amounts['non_contributor_tax'] = $sign * $tax;
            $amounts['total'] = $sign * ($exempt + $base + $tax);

            if ($bucket === 'general') {
                $amounts['igtf_base'] = $sign * $buckets['igtf'][0];
                $amounts['igtf_tax'] = $sign * $buckets['igtf'][1];
            }

            $rows[] = $this->row($context, array_merge($meta, [
                'customer_rif' => 'NO SUMINISTRO',
                'taxpayer_type' => 'NO CONTRIBUYENTE',
                'affected_document' => null,
                'bucket' => $bucket,
                'vat_rate' => $this->rateFor($zReport, $bucket),
                'amounts' => $amounts,
                'sort_number' => $index,
            ]));
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $doc
     * @param  array<string, mixed>  $buckets
     * @return array<string, mixed>
     */
    private function documentRow(array $context, array $doc, array $buckets, string $type, int $sign, int $sortOrder): array
    {
        /** @var FiscalDocument $document */
        $document = $doc['document'];
        [$base, $tax] = $buckets['general'];

        $amounts = $this->emptyAmounts();
        $amounts['exempt'] = $sign * $buckets['exempt'];
        $amounts['contributor_base'] = $sign * $base;
        $amounts['contributor_tax'] = $sign * $tax;
        $amounts['total'] = $sign * ($buckets['exempt'] + $base + $tax);

        $date = ($document->printer_datetime ?? $document->printed_at)?->toDateString() ?? $context['date'];

        return $this->row(array_merge($context, ['date' => $date]), [
            'document_type' => $type,
            'first_document' => $this->pad($doc['number'], 8),
            'last_document' => $this->pad($doc['number'], 8),
            'affected_document' => $type === 'NOTA DE CRÉDITO'
                ? (string) ($doc['payload']['original_invoice']['fiscal_number'] ?? '')
                : null,
            'customer_name' => $doc['customer_name'],
            'customer_rif' => $doc['customer_rif'],
            'taxpayer_type' => 'CONTRIBUYENTE',
            'bucket' => 'general',
            'vat_rate' => $this->rateFor($context['z'], 'general'),
            'amounts' => $amounts,
            'fiscal_printer_id' => $document->fiscal_printer_id,
            'sort_order' => $sortOrder,
            'sort_number' => $doc['number'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function row(array $context, array $data): array
    {
        /** @var FiscalZReport|null $zReport */
        $zReport = $context['z'] ?? null;

        return array_merge([
            'date' => $context['date'],
            'printer_serial' => $context['serial'],
            'z_number' => $zReport?->z_number,
            'fiscal_printer_id' => $zReport?->fiscal_printer_id,
            'retention_issued_on' => null,
            'retention_voucher' => null,
            'sort_date' => $context['date'],
            'sort_serial' => (string) $context['serial'],
            'sort_z' => (int) ($zReport?->z_number ?? PHP_INT_MAX),
            'sort_number' => 0,
        ], $data);
    }

    /**
     * Comprobantes de retención recibidos en la quincena: en la línea de su factura si está en este libro; si no, en línea propia.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachRetentions(array $rows, Branch $branch, SalesBookPeriod $period): array
    {
        $retentions = SalesVatRetention::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('received_on', [$period->start()->toDateString(), $period->end()->toDateString()])
            ->orderBy('received_on')
            ->orderBy('id')
            ->get();

        foreach ($retentions as $retention) {
            $invoiceNumber = (int) preg_replace('/\D/', '', (string) $retention->invoice_number);
            $retained = $this->cents($retention->retained_amount_ves);

            if ($retained > $this->cents($retention->invoice_tax_ves)) {
                $this->issue(self::LEVEL_ERROR, 'Retención '.$retention->voucher_number.': el monto retenido supera el IVA de la factura '.$retention->invoice_number.'.');
            }

            $matched = false;

            foreach ($rows as $index => $row) {
                if ($row['document_type'] !== 'FACTURA' || $row['retention_voucher'] !== null
                    || (int) $row['last_document'] !== $invoiceNumber
                    || ($retention->fiscal_printer_id !== null && (int) $row['fiscal_printer_id'] !== (int) $retention->fiscal_printer_id)) {
                    continue;
                }

                $rows[$index]['retention_issued_on'] = $retention->voucher_issued_on->toDateString();
                $rows[$index]['retention_voucher'] = $retention->voucher_number;
                $rows[$index]['amounts']['retained'] = $retained;
                $matched = true;

                break;
            }

            if ($matched) {
                continue;
            }

            $amounts = $this->emptyAmounts();
            $amounts['retained'] = $retained;
            $date = $retention->received_on->toDateString();

            $rows[] = $this->row(['date' => $date, 'serial' => (string) ($retention->fiscalPrinter?->fiscal_registry ?? ''), 'z' => null], [
                'document_type' => 'COMPROBANTE DE RETENCIÓN',
                'first_document' => null,
                'last_document' => null,
                'affected_document' => (string) $retention->invoice_number,
                'customer_name' => mb_strtoupper((string) $retention->customer_name),
                'customer_rif' => VenezuelanRifFormatter::format((string) $retention->customer_rif),
                'taxpayer_type' => 'CONTRIBUYENTE',
                'bucket' => 'general',
                'vat_rate' => null,
                'amounts' => $amounts,
                'retention_issued_on' => $retention->voucher_issued_on->toDateString(),
                'retention_voucher' => $retention->voucher_number,
                'sort_serial' => '~',
                'sort_order' => 9,
                'sort_number' => (int) $retention->id,
            ]);

            if (! $retention->invoice_date->lt($period->start())) {
                $this->issue(self::LEVEL_WARNING, 'Retención '.$retention->voucher_number.': no se encontró en este libro la factura a contribuyente '.$retention->invoice_number.'; se registra en línea propia.');
            }
        }

        return $rows;
    }

    /**
     * Verificación cruzada: el libro debe sumar exactamente lo que dicen los Z (sin retenciones).
     *
     * @param  Collection<int, FiscalZReport>  $zReports
     * @param  array<string, int>  $totals
     */
    private function checkTotalsAgainstZ(Collection $zReports, array $totals): void
    {
        $expected = 0;

        foreach ($zReports as $zReport) {
            foreach (['sales' => 1, 'credit_notes' => -1, 'debit_notes' => 1] as $group => $sign) {
                $buckets = $this->zBuckets($zReport, $group);
                $expected += $sign * $buckets['exempt'];

                foreach (self::BUCKETS as $bucket) {
                    $expected += $sign * ($buckets[$bucket][0] + $buckets[$bucket][1]);
                }
            }
        }

        if ($expected !== $totals['total']) {
            $this->issue(self::LEVEL_ERROR, 'El total del libro (Bs '.$this->format($totals['total']).') no cuadra con la suma de los reportes Z (Bs '.$this->format($expected).').');
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function header(Branch $branch, SalesBookPeriod $period): array
    {
        $companyName = mb_strtoupper(trim((string) config('fiscal.retention_agent.name')));
        $branchAddress = trim(implode(' ', array_filter([(string) $branch->address, (string) $branch->city])));

        return [
            'company_name' => $companyName,
            'company_rif' => VenezuelanRifFormatter::format((string) config('fiscal.retention_agent.rif')),
            'branch_name' => mb_strtoupper((string) $branch->name),
            'branch_address' => mb_strtoupper($branchAddress !== '' ? $branchAddress : (string) $branch->name),
            'month_name' => $period->monthName(),
            'year' => $period->year,
            'half_label' => mb_strtoupper($period->halfLabel()),
            'range_label' => $period->rangeLabel(),
            'igtf_rate' => (string) DefaultIgtfRate::percent(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row): array
    {
        return [
            'operation_number' => $row['operation_number'],
            'date' => $row['date'],
            'document_type' => $row['document_type'],
            'first_document' => $row['first_document'],
            'last_document' => $row['last_document'],
            'affected_document' => $row['affected_document'],
            'customer_name' => $row['customer_name'],
            'customer_rif' => $row['customer_rif'],
            'taxpayer_type' => $row['taxpayer_type'],
            'vat_rate' => $row['vat_rate'],
            'bucket' => $row['bucket'],
            'printer_serial' => $row['printer_serial'],
            'z_number' => $row['z_number'],
            'retention_issued_on' => $row['retention_issued_on'],
            'retention_voucher' => $row['retention_voucher'],
            'amounts' => array_map(fn (int $cents): float => $this->money($cents), $row['amounts']),
        ];
    }

    /**
     * Resumen de débitos fiscales con los ítems de la Forma 30.
     *
     * @param  array<string, int>  $cents
     * @return array<string, float>
     */
    private function summary(array $cents): array
    {
        $item46 = $cents['exempt'] + $cents['export'] + $cents['general_base'] + $cents['additional_base'] + $cents['reduced_base'];
        $item47 = $cents['general_tax'] + $cents['additional_tax'] + $cents['reduced_tax'];

        return [
            'item_40' => $this->money($cents['exempt']),
            'item_41' => $this->money($cents['export']),
            'item_42' => $this->money($cents['general_base']),
            'item_43' => $this->money($cents['general_tax']),
            'item_442' => $this->money($cents['additional_base']),
            'item_452' => $this->money($cents['additional_tax']),
            'item_443' => $this->money($cents['reduced_base']),
            'item_453' => $this->money($cents['reduced_tax']),
            'item_46' => $this->money($item46),
            'item_47' => $this->money($item47),
            'item_66' => $this->money($cents['retained']),
        ];
    }

    private function rateFor(FiscalZReport $zReport, string $bucket): ?float
    {
        $column = $bucket.'_rate_percent';
        $value = $zReport->{$column};

        if ($value !== null) {
            return (float) $value;
        }

        return $bucket === 'general' ? (float) DefaultVatRate::percent() : null;
    }

    /**
     * @return array<string, int>
     */
    private function emptyAmounts(): array
    {
        return array_fill_keys(self::AMOUNT_KEYS, 0);
    }

    private function issue(string $level, string $message): void
    {
        $this->issues[] = ['level' => $level, 'message' => $message];
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function money(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    private function pad(int $number, int $length): string
    {
        return str_pad((string) $number, $length, '0', STR_PAD_LEFT);
    }
}
