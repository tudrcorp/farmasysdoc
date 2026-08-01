<?php

namespace App\Services\Hr;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PayrollPeriodReportExporter
{
    public function __construct(
        private PayrollPeriodReportBuilder $builder,
    ) {}

    public function streamCsv(PayrollPeriod $period): StreamedResponse
    {
        $this->assertExportable($period);

        $payload = $this->builder->build($period);
        $fileName = $this->fileName($period, 'csv');

        return response()->streamDownload(function () use ($payload): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");

            /** @var PayrollPeriod $period */
            $period = $payload['period'];
            $totals = $payload['totals'];

            fputcsv($stream, ['Reporte de nómina'], ';');
            fputcsv($stream, ['Periodo', $period->label()], ';');
            fputcsv($stream, ['Quincena', $period->halfLabel()], ';');
            fputcsv($stream, ['Mes', $period->monthLabel()], ';');
            fputcsv($stream, ['Fecha periodo', $period->period_date->format('d/m/Y')], ';');
            fputcsv($stream, ['Estatus', $period->status?->label() ?? '—'], ';');
            fputcsv($stream, [
                'Tasa BCV',
                $period->bcv_ves_per_usd !== null
                    ? number_format((float) $period->bcv_ves_per_usd, 6, ',', '.')
                    : '—',
            ], ';');
            fputcsv($stream, ['Generado', $payload['generated_at'].' por '.$payload['generated_by']], ';');
            fputcsv($stream, [], ';');

            fputcsv($stream, ['Totales'], ';');
            fputcsv($stream, ['Empleados', (string) $totals['employees']], ';');
            fputcsv($stream, ['Base USD', $this->num($totals['base_salary_usd'])], ';');
            fputcsv($stream, ['Asignaciones USD', $this->num($totals['assignments_usd'])], ';');
            fputcsv($stream, ['Deducciones USD', $this->num($totals['deductions_usd'])], ';');
            fputcsv($stream, ['Préstamos USD', $this->num($totals['loans_usd'])], ';');
            fputcsv($stream, ['Pagar USD', $this->num($totals['cash_paid_usd'])], ';');
            fputcsv($stream, ['Pagar Bs', $this->num($totals['cash_paid_ves'])], ';');
            fputcsv($stream, ['Neto contable USD', $this->num($totals['net_usd'])], ';');
            fputcsv($stream, ['Neto contable Bs', $this->num($totals['net_ves'])], ';');
            fputcsv($stream, [], ';');

            fputcsv($stream, $this->detailHeaders(), ';');

            /** @var PayrollLine $line */
            foreach ($payload['lines'] as $line) {
                fputcsv($stream, $this->detailRow($line), ';');
            }

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function streamPdf(PayrollPeriod $period): StreamedResponse
    {
        $this->assertExportable($period);

        $payload = $this->builder->build($period);
        $fileName = $this->fileName($period, 'pdf');

        $pdf = Pdf::loadView('pdf.payroll-period-report', $payload)
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $fileName,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function assertExportable(PayrollPeriod $period): void
    {
        if (! in_array($period->status, [PayrollPeriodStatus::Calculated, PayrollPeriodStatus::Closed], true)) {
            throw new InvalidArgumentException('Calcule la nómina antes de exportar el reporte.');
        }

        if (! $period->lines()->exists()) {
            throw new InvalidArgumentException('El periodo no tiene líneas de nómina para exportar.');
        }
    }

    private function fileName(PayrollPeriod $period, string $extension): string
    {
        $date = $period->period_date->format('Y-m-d');

        return "nomina-periodo-{$period->period_number}-{$date}.".ltrim($extension, '.');
    }

    /**
     * @return list<string>
     */
    private function detailHeaders(): array
    {
        return [
            'Empleado',
            'Cédula',
            'Sucursal',
            'Base USD',
            'Asignaciones USD',
            'Deducciones USD',
            'Préstamos USD',
            'Porción USD efectivo',
            'Porción Bs (USD)',
            'Pagar USD',
            'Pagar Bs',
            'Neto contable USD',
            'Neto contable Bs',
            'Tasa BCV',
        ];
    }

    /**
     * @return list<string>
     */
    private function detailRow(PayrollLine $line): array
    {
        return [
            $line->employee?->fullName() ?? '—',
            (string) ($line->employee?->national_id ?? '—'),
            (string) ($line->employee?->branch?->name ?? '—'),
            $this->num((float) $line->base_salary_usd),
            $this->num((float) $line->assignments_usd),
            $this->num((float) $line->deductions_usd),
            $this->num((float) $line->loans_usd),
            $this->num((float) $line->usd_cash_portion),
            $this->num((float) $line->ves_portion_usd),
            $this->num((float) $line->cash_paid_usd),
            $this->num((float) $line->cash_paid_ves),
            $this->num((float) $line->net_usd),
            $this->num((float) $line->net_ves),
            number_format((float) $line->bcv_ves_per_usd, 6, ',', '.'),
        ];
    }

    private function num(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
