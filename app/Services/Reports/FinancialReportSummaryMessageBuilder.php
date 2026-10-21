<?php

namespace App\Services\Reports;

final class FinancialReportSummaryMessageBuilder
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function build(array $report): string
    {
        $period = (string) ($report['period']['label'] ?? '—');
        $branch = (string) ($report['filters']['branch_label'] ?? 'Todas las sucursales');
        $income = $report['income'] ?? [];
        $expenses = $report['expenses'] ?? [];
        $net = $report['net'] ?? [];
        $netCash = $report['net_cash'] ?? [];

        $lines = [
            'REPORTE FINANCIERO — FARMADOC',
            'Período: '.$period,
            'Sucursal: '.$branch,
            '',
            'RESUMEN',
            'Ingresos facturados: '.FinancialReportBuilder::formatUsd((float) ($income['total']['usd'] ?? 0))
                .' · '.FinancialReportBuilder::formatVes((float) ($income['total']['ves'] ?? 0)).' cobrados',
            'Egresos (devengado): '.FinancialReportBuilder::formatUsd((float) ($expenses['total_accrual']['usd'] ?? 0))
                .' · '.FinancialReportBuilder::formatVes((float) ($expenses['total_accrual']['ves'] ?? 0)),
            'Resultado neto: '.FinancialReportBuilder::formatUsd((float) ($net['usd'] ?? 0))
                .' · '.FinancialReportBuilder::formatVes((float) ($net['ves'] ?? 0)),
            'Flujo de caja: '.FinancialReportBuilder::formatVes((float) ($netCash['ves'] ?? 0)),
            '',
            'INGRESOS',
            $this->line($income['counter_sales'] ?? [], 'Ventas por mostrador'),
            $this->line($income['corporate_sales'] ?? [], 'Ventas corporativas'),
            $this->line($income['accounts_receivable'] ?? [], 'CxC cobradas'),
        ];

        foreach ($income['payment_channels'] ?? [] as $channel) {
            if (! is_array($channel)) {
                continue;
            }
            $lines[] = sprintf(
                '%s (%d): %s · %s',
                (string) ($channel['label'] ?? 'Canal'),
                (int) ($channel['count'] ?? 0),
                FinancialReportBuilder::formatUsd((float) ($channel['payment_usd'] ?? 0)),
                FinancialReportBuilder::formatVes((float) ($channel['payment_ves'] ?? 0)),
            );
        }

        $lines[] = '';
        $lines[] = 'EGRESOS (devengado)';
        $lines[] = $this->line($expenses['purchases'] ?? [], 'Compras recibidas');
        $lines[] = $this->line($expenses['fixed']['payroll'] ?? [], 'Sueldos y salarios');
        foreach ($expenses['fixed']['services']['by_category'] ?? [] as $categoryRow) {
            if (! is_array($categoryRow)) {
                continue;
            }
            $lines[] = $this->line($categoryRow, (string) ($categoryRow['label'] ?? 'Gasto fijo'));
        }
        $lines[] = $this->line($expenses['variable']['petty_cash'] ?? [], 'Caja chica');
        $lines[] = '';
        $lines[] = 'FISCAL / FLUJO';
        $lines[] = $this->line($expenses['seniat_retentions'] ?? [], 'Retenciones IVA (SENIAT)');
        $lines[] = $this->line($expenses['accounts_payable'] ?? [], 'CxP pagadas');
        $lines[] = 'Total desembolsos: '.FinancialReportBuilder::formatUsd((float) ($expenses['total_cash']['usd'] ?? 0))
            .' · '.FinancialReportBuilder::formatVes((float) ($expenses['total_cash']['ves'] ?? 0));
        $lines[] = '';
        $lines[] = 'Adjunto: Excel con el detalle completo.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function line(array $row, string $fallbackLabel): string
    {
        $label = (string) ($row['label'] ?? $fallbackLabel);
        $count = (int) ($row['count'] ?? 0);
        $usd = FinancialReportBuilder::formatUsd((float) ($row['usd'] ?? 0));
        $ves = FinancialReportBuilder::formatVes((float) ($row['ves'] ?? 0));

        return sprintf('%s (%d): %s · %s', $label, $count, $usd, $ves);
    }
}
