<?php

namespace App\Services\Finance;

use App\Enums\PurchaseLedgerDocumentType;
use App\Models\PurchaseLedger;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class PurchaseLedgerBookReportBuilder
{
    public const HALF_FIRST = 'first';

    public const HALF_SECOND = 'second';

    public const HALF_MONTH = 'month';

    /**
     * @return array<string, string>
     */
    public static function halfOptions(): array
    {
        return [
            self::HALF_FIRST => 'Primera quincena (1–15)',
            self::HALF_SECOND => 'Segunda quincena (16–fin de mes)',
            self::HALF_MONTH => 'Mes completo',
        ];
    }

    /**
     * @return array{
     *     company_name: string,
     *     company_rif: string,
     *     tax_period: string,
     *     half: string,
     *     month_label: string,
     *     year: int,
     *     range_from: Carbon,
     *     range_until: Carbon,
     *     period_title: string,
     *     rows: list<array<string, mixed>>,
     *     totals: array{
     *         taxable_base_16: float,
     *         tax_caused_16: float,
     *         exempt: float,
     *         total_with_vat: float,
     *         retention: float
     *     },
     *     generated_at: string,
     *     generated_by: string
     * }
     */
    public function build(string $taxPeriod, string $half, ?string $generatedBy = null): array
    {
        if (preg_match('/^\d{4}\/\d{2}$/', $taxPeriod) !== 1) {
            throw new InvalidArgumentException('El periodo debe tener el formato YYYY/MM.');
        }

        if (! in_array($half, [self::HALF_FIRST, self::HALF_SECOND, self::HALF_MONTH], true)) {
            throw new InvalidArgumentException('La quincena indicada no es válida.');
        }

        [$from, $until] = $this->rangeFor($taxPeriod, $half);

        /** @var Collection<int, PurchaseLedger> $ledgers */
        $ledgers = PurchaseLedger::query()
            ->where('tax_period', $taxPeriod)
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $until->toDateString())
            ->orderBy('operation_number')
            ->get();

        $purchaseIdsWithVat = $ledgers
            ->filter(fn (PurchaseLedger $row): bool => $row->document_type === PurchaseLedgerDocumentType::Factura
                && $this->hasCausedVat($row))
            ->map(fn (PurchaseLedger $row): int => (int) $row->purchase_id)
            ->unique()
            ->all();

        $ledgers = $ledgers
            ->filter(function (PurchaseLedger $row) use ($purchaseIdsWithVat): bool {
                if ($row->document_type === PurchaseLedgerDocumentType::Factura) {
                    return $this->hasCausedVat($row);
                }

                if ($row->document_type !== PurchaseLedgerDocumentType::ComprobanteDeRetencion) {
                    return false;
                }

                $purchaseId = (int) $row->purchase_id;

                return in_array($purchaseId, $purchaseIdsWithVat, true) || $this->hasCausedVat($row);
            })
            ->values();

        $facturasByPurchaseId = $ledgers
            ->filter(fn (PurchaseLedger $row): bool => $row->document_type === PurchaseLedgerDocumentType::Factura)
            ->keyBy(fn (PurchaseLedger $row): int => (int) $row->purchase_id);

        $totals = [
            'taxable_base_16' => 0.0,
            'tax_caused_16' => 0.0,
            'exempt' => 0.0,
            'total_with_vat' => 0.0,
            'retention' => 0.0,
        ];

        $rows = [];

        foreach ($ledgers as $ledger) {
            $isRetention = $ledger->document_type === PurchaseLedgerDocumentType::ComprobanteDeRetencion;
            $factura = $isRetention
                ? $facturasByPurchaseId->get((int) $ledger->purchase_id)
                : $ledger;

            $documentNumber = $isRetention
                ? (string) ($factura?->document_number ?: $ledger->document_number)
                : (string) $ledger->document_number;
            $controlNumber = $isRetention
                ? (string) ($factura?->control_number ?: $ledger->control_number ?: '')
                : (string) ($ledger->control_number ?? '');

            if (! $isRetention) {
                $totals['taxable_base_16'] += (float) ($ledger->taxable_base_ves ?? 0);
                $totals['tax_caused_16'] += (float) ($ledger->tax_caused_ves ?? 0);
                $totals['exempt'] += (float) ($ledger->exempt_amount_ves ?? 0);
                $totals['total_with_vat'] += (float) ($ledger->total_with_vat_and_exempt_ves ?? 0);
            } else {
                $totals['retention'] += (float) ($ledger->retention_amount_ves ?? 0);
            }

            $rows[] = [
                'operation_number' => (int) $ledger->operation_number,
                'invoice_date' => $ledger->invoice_date?->format('d/m/Y') ?? '',
                'document_type' => $ledger->document_type?->label() ?? '',
                'is_retention' => $isRetention,
                'document_number' => $documentNumber,
                'control_number' => $controlNumber,
                'serie' => '',
                'supplier_name' => (string) $ledger->supplier_name,
                'supplier_rif' => VenezuelanRifFormatter::format($ledger->supplier_tax_id) ?: (string) $ledger->supplier_tax_id,
                'taxpayer_type' => (string) ($ledger->taxpayer_type ?? ''),
                'total_with_vat' => $isRetention ? null : (float) ($ledger->total_with_vat_and_exempt_ves ?? 0),
                'exempt' => $isRetention ? null : ($ledger->exempt_amount_ves !== null ? (float) $ledger->exempt_amount_ves : null),
                'export' => $isRetention ? null : ($ledger->export_amount_ves !== null ? (float) $ledger->export_amount_ves : null),
                'taxable_base' => $isRetention ? null : (float) ($ledger->taxable_base_ves ?? 0),
                'tax_caused' => $isRetention ? null : (float) ($ledger->tax_caused_ves ?? 0),
                'taxable_base_reduced' => $isRetention ? null : ($ledger->taxable_base_reduced_ves !== null ? (float) $ledger->taxable_base_reduced_ves : null),
                'tax_reduced' => $isRetention ? null : ($ledger->tax_reduced_ves !== null ? (float) $ledger->tax_reduced_ves : null),
                'vat_rate_percent' => $ledger->vat_rate_percent !== null ? (float) $ledger->vat_rate_percent : null,
                'retention_issued_at' => $isRetention
                    ? ($ledger->retention_voucher_issued_at?->format('d/m/Y') ?: $ledger->invoice_date?->format('d/m/Y'))
                    : null,
                'retention_voucher_number' => $isRetention ? $ledger->retention_voucher_number : null,
                'retention_amount' => $isRetention ? (float) ($ledger->retention_amount_ves ?? 0) : null,
            ];
        }

        [$year, $month] = array_map('intval', explode('/', $taxPeriod));

        return [
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => VenezuelanRifFormatter::format((string) config('fiscal.retention_agent.rif'))
                ?: (string) config('fiscal.retention_agent.rif'),
            'tax_period' => $taxPeriod,
            'half' => $half,
            'month_label' => $this->monthLabel($month),
            'year' => $year,
            'range_from' => $from,
            'range_until' => $until,
            'period_title' => $this->periodTitle($half, $from, $until),
            'rows' => $rows,
            'totals' => [
                'taxable_base_16' => round($totals['taxable_base_16'], 2),
                'tax_caused_16' => round($totals['tax_caused_16'], 2),
                'exempt' => round($totals['exempt'], 2),
                'total_with_vat' => round($totals['total_with_vat'], 2),
                'retention' => round($totals['retention'], 2),
            ],
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'generated_by' => $generatedBy ?: 'sistema',
        ];
    }

    /**
     * El libro SENIAT solo incluye compras con impuesto causado (IVA > 0).
     */
    private function hasCausedVat(PurchaseLedger $row): bool
    {
        return round((float) ($row->tax_caused_ves ?? 0), 2) > 0;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangeFor(string $taxPeriod, string $half): array
    {
        [$year, $month] = array_map('intval', explode('/', $taxPeriod));
        $start = Carbon::parse(sprintf('%04d-%02d-01', $year, $month))->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();

        return match ($half) {
            self::HALF_FIRST => [$start, $start->copy()->day(15)],
            self::HALF_SECOND => [$start->copy()->day(16), $end],
            default => [$start, $end],
        };
    }

    private function periodTitle(string $half, Carbon $from, Carbon $until): string
    {
        $span = $from->format('d-m-Y').' AL '.$until->format('d-m-Y');

        return match ($half) {
            self::HALF_FIRST => 'PRIMERA QUINCENA : '.$span,
            self::HALF_SECOND => 'SEGUNDA QUINCENA : '.$span,
            default => 'MES : '.$span,
        };
    }

    private function monthLabel(int $month): string
    {
        return match ($month) {
            1 => 'ENERO',
            2 => 'FEBRERO',
            3 => 'MARZO',
            4 => 'ABRIL',
            5 => 'MAYO',
            6 => 'JUNIO',
            7 => 'JULIO',
            8 => 'AGOSTO',
            9 => 'SEPTIEMBRE',
            10 => 'OCTUBRE',
            11 => 'NOVIEMBRE',
            12 => 'DICIEMBRE',
            default => '',
        };
    }
}
