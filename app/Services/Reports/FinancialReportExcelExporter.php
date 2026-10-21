<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class FinancialReportExcelExporter
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function generate(array $report): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte financiero');

        $this->addLogo($sheet);
        $row = 6;

        $periodLabel = (string) ($report['period']['label'] ?? '');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Reporte financiero');
        $this->styleTitle($sheet, "A{$row}");
        $row++;

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Período: '.$periodLabel.' · Sucursal: '.((string) ($report['filters']['branch_label'] ?? 'Todas')));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        $income = $report['income'] ?? [];
        $expenses = $report['expenses'] ?? [];
        $net = $report['net'] ?? [];
        $netCash = $report['net_cash'] ?? [];

        $row = $this->writeSummaryCards($sheet, $row, $income, $expenses, $net, $netCash);
        $row += 2;

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", (string) ($report['columns']['usd_label'] ?? 'USD').' · '.(string) ($report['columns']['ves_label'] ?? 'Bs.'));
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9);
        $row += 2;

        $incomeEndRow = $this->writeIncomeColumn($sheet, $row, $income);
        $expenseEndRow = $this->writeExpenseColumn($sheet, $row, $expenses);
        $row = max($incomeEndRow, $expenseEndRow) + 2;

        $byBranch = $report['by_branch'] ?? [];
        if (is_array($byBranch) && $byBranch !== []) {
            $row = $this->writeBranchBreakdownTable($sheet, $row, $byBranch);
            $row += 2;
        }

        $fixedItems = $report['fixed_service_items'] ?? [];
        if (is_array($fixedItems) && $fixedItems !== []) {
            $row = $this->writeFixedItemsTable($sheet, $row, $fixedItems);
            $row += 2;
        }

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Los ingresos en USD reflejan el total facturado. La columna Bs. muestra cobro/pago físico. Compras y CxP no se suman juntas.');
        $sheet->getStyle("A{$row}")->getFont()->setSize(9)->setItalic(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true);

        foreach (['A' => 34, 'B' => 14, 'C' => 16, 'D' => 34, 'E' => 14, 'F' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $bytes = ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return is_string($bytes) ? $bytes : '';
    }

    /**
     * @param  array<string, mixed>  $income
     * @param  array<string, mixed>  $expenses
     * @param  array<string, mixed>  $net
     * @param  array<string, mixed>  $netCash
     */
    private function writeSummaryCards(
        Worksheet $sheet,
        int $row,
        array $income,
        array $expenses,
        array $net,
        array $netCash,
    ): int {
        $cards = [
            ['Ingresos facturados', (float) ($income['total']['usd'] ?? 0), (float) ($income['total']['ves'] ?? 0), '06B6D4', 'cobrados'],
            ['Egresos (devengado)', (float) ($expenses['total_accrual']['usd'] ?? 0), (float) ($expenses['total_accrual']['ves'] ?? 0), 'F43F5E', ''],
            ['Resultado neto (devengado)', (float) ($net['usd'] ?? 0), (float) ($net['ves'] ?? 0), 'FACC15', ''],
            ['Resultado flujo de caja', (float) ($netCash['usd'] ?? 0), (float) ($netCash['ves'] ?? 0), '6366F1', 'Cobros − desembolsos'],
        ];

        foreach (['Concepto', 'USD', 'Bs.'] as $index => $header) {
            $col = chr(65 + $index);
            $sheet->setCellValue("{$col}{$row}", $header);
            $this->styleHeader($sheet, "{$col}{$row}", '374151', 'FFFFFF');
        }
        $row++;

        foreach ($cards as $card) {
            $sheet->setCellValue("A{$row}", $card[0]);
            $this->writeMoney($sheet, "B{$row}", $card[1]);
            $this->writeMoney($sheet, "C{$row}", $card[2]);
            if ($card[4] !== '') {
                $sheet->setCellValue("D{$row}", $card[4]);
            }
            $this->styleCardRow($sheet, "A{$row}:C{$row}", $card[3]);
            $row++;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $income
     */
    private function writeIncomeColumn(Worksheet $sheet, int $row, array $income): int
    {
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'INGRESOS');
        $this->styleSectionHeader($sheet, "A{$row}:C{$row}", '06B6D4');
        $row++;

        $sheet->setCellValue("B{$row}", 'USD');
        $sheet->setCellValue("C{$row}", 'Bs.');
        $this->styleHeader($sheet, "B{$row}:C{$row}", 'E5E7EB', '111827');
        $row++;

        foreach (['counter_sales', 'corporate_sales', 'accounts_receivable'] as $key) {
            $row = $this->writeDetailBlock($sheet, $row, 'A', $income[$key] ?? []);
        }

        $channels = $income['payment_channels'] ?? [];
        if (is_array($channels) && $channels !== []) {
            $row = $this->writeGroupLabel($sheet, $row, 'A', 'COBROS POR MÉTODO (MOSTRADOR)');
            foreach ($channels as $channel) {
                if (! is_array($channel)) {
                    continue;
                }
                $row = $this->writeDetailBlock($sheet, $row, 'A', [
                    'label' => (string) ($channel['label'] ?? ''),
                    'count' => (int) ($channel['count'] ?? 0),
                    'usd' => (float) ($channel['payment_usd'] ?? 0),
                    'ves' => (float) ($channel['payment_ves'] ?? 0),
                    'meta' => 'ventas mostrador',
                ]);
            }
        }

        return $this->writeTotalBlock($sheet, $row, 'A', 'Total ingresos', $income['total'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $expenses
     */
    private function writeExpenseColumn(Worksheet $sheet, int $row, array $expenses): int
    {
        $sheet->mergeCells("D{$row}:F{$row}");
        $sheet->setCellValue("D{$row}", 'EGRESOS');
        $this->styleSectionHeader($sheet, "D{$row}:F{$row}", 'F43F5E');
        $row++;

        $sheet->setCellValue("E{$row}", 'USD');
        $sheet->setCellValue("F{$row}", 'Bs.');
        $this->styleHeader($sheet, "E{$row}:F{$row}", 'E5E7EB', '111827');
        $row++;

        $row = $this->writeDetailBlock($sheet, $row, 'D', $expenses['purchases'] ?? []);

        $row = $this->writeGroupLabel($sheet, $row, 'D', 'GASTOS FIJOS');
        $row = $this->writeDetailBlock($sheet, $row, 'D', $expenses['fixed']['payroll'] ?? []);
        foreach ($expenses['fixed']['services']['by_category'] ?? [] as $categoryRow) {
            if (! is_array($categoryRow)) {
                continue;
            }
            $row = $this->writeDetailBlock($sheet, $row, 'D', $categoryRow);
        }

        $row = $this->writeGroupLabel($sheet, $row, 'D', 'GASTOS VARIABLES');
        $row = $this->writeDetailBlock($sheet, $row, 'D', $expenses['variable']['petty_cash'] ?? []);

        $row = $this->writeTotalBlock($sheet, $row, 'D', 'Total egresos (devengado)', $expenses['total_accrual'] ?? []);

        $row = $this->writeGroupLabel($sheet, $row, 'D', 'FISCAL / FLUJO (no sumado arriba)', true);
        $row = $this->writeDetailBlock($sheet, $row, 'D', $expenses['seniat_retentions'] ?? [], true);
        $row = $this->writeDetailBlock($sheet, $row, 'D', $expenses['accounts_payable'] ?? [], true);

        return $this->writeTotalBlock($sheet, $row, 'D', 'Total desembolsos (flujo)', $expenses['total_cash'] ?? [], true);
    }

    /**
     * @param  array<string, mixed>  $rowData
     */
    private function writeDetailBlock(
        Worksheet $sheet,
        int $row,
        string $labelCol,
        array $rowData,
        bool $cashFlow = false,
    ): int {
        $usdCol = $labelCol === 'A' ? 'B' : 'E';
        $vesCol = $labelCol === 'A' ? 'C' : 'F';
        $label = (string) ($rowData['label'] ?? '');
        $meta = (string) ($rowData['meta'] ?? '');
        $count = (int) ($rowData['count'] ?? 0);

        $sheet->setCellValue("{$labelCol}{$row}", $label);
        $sheet->getStyle("{$labelCol}{$row}")->getFont()->setBold(true);
        $this->writeMoney($sheet, "{$usdCol}{$row}", (float) ($rowData['usd'] ?? 0));
        $this->writeMoney($sheet, "{$vesCol}{$row}", (float) ($rowData['ves'] ?? 0));

        $metaRow = $row + 1;
        $sheet->setCellValue("{$labelCol}{$metaRow}", $count.' operaciones'.($meta !== '' ? ' · '.$meta : ''));
        $sheet->getStyle("{$labelCol}{$metaRow}")->getFont()->setSize(9)->setItalic(true);

        if ($cashFlow) {
            $sheet->getStyle("{$labelCol}{$row}:{$vesCol}{$metaRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDE9FE');
        }

        return $metaRow + 1;
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    private function writeTotalBlock(
        Worksheet $sheet,
        int $row,
        string $labelCol,
        string $label,
        array $totals,
        bool $cashFlow = false,
    ): int {
        $usdCol = $labelCol === 'A' ? 'B' : 'E';
        $vesCol = $labelCol === 'A' ? 'C' : 'F';

        $sheet->setCellValue("{$labelCol}{$row}", $label);
        $sheet->getStyle("{$labelCol}{$row}")->getFont()->setBold(true);
        $this->writeMoney($sheet, "{$usdCol}{$row}", (float) ($totals['usd'] ?? 0));
        $this->writeMoney($sheet, "{$vesCol}{$row}", (float) ($totals['ves'] ?? 0));
        $sheet->getStyle("{$labelCol}{$row}:{$vesCol}{$row}")->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_MEDIUM);

        if ($cashFlow) {
            $sheet->getStyle("{$labelCol}{$row}:{$vesCol}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF4F4F5');
        }

        return $row + 1;
    }

    private function writeGroupLabel(Worksheet $sheet, int $row, string $labelCol, string $label, bool $italic = false): int
    {
        $endCol = $labelCol === 'A' ? 'C' : 'F';
        $sheet->mergeCells("{$labelCol}{$row}:{$endCol}{$row}");
        $sheet->setCellValue("{$labelCol}{$row}", $label);
        $style = $sheet->getStyle("{$labelCol}{$row}")->getFont()->setBold(true)->setSize(9);
        if ($italic) {
            $style->setItalic(true);
        }

        return $row + 1;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeBranchBreakdownTable(Worksheet $sheet, int $row, array $rows): int
    {
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'Resultado por sucursal');
        $this->styleSectionHeader($sheet, "A{$row}:G{$row}", '374151');
        $row++;

        $headers = ['Sucursal', 'Ing. USD', 'Ing. Bs.', 'Egr. USD', 'Egr. Bs.', 'Neto USD', 'Neto Bs.'];
        foreach ($headers as $index => $header) {
            $col = chr(65 + $index);
            $sheet->setCellValue("{$col}{$row}", $header);
            $this->styleHeader($sheet, "{$col}{$row}", 'E5E7EB', '111827');
        }
        $row++;

        foreach ($rows as $branchRow) {
            $sheet->setCellValue("A{$row}", (string) ($branchRow['branch'] ?? ''));
            $this->writeMoney($sheet, "B{$row}", (float) ($branchRow['income_usd'] ?? 0));
            $this->writeMoney($sheet, "C{$row}", (float) ($branchRow['income_ves'] ?? 0));
            $this->writeMoney($sheet, "D{$row}", (float) ($branchRow['expense_usd'] ?? 0));
            $this->writeMoney($sheet, "E{$row}", (float) ($branchRow['expense_ves'] ?? 0));
            $this->writeMoney($sheet, "F{$row}", (float) ($branchRow['net_usd'] ?? 0));
            $this->writeMoney($sheet, "G{$row}", (float) ($branchRow['net_ves'] ?? 0));
            $row++;
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function writeFixedItemsTable(Worksheet $sheet, int $row, array $items): int
    {
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Servicios fijos del período');
        $this->styleSectionHeader($sheet, "A{$row}:F{$row}", '374151');
        $row++;

        $headers = ['Fecha', 'Categoría', 'Descripción', 'Sucursal', 'USD', 'Bs.'];
        foreach ($headers as $index => $header) {
            $col = chr(65 + $index);
            $sheet->setCellValue("{$col}{$row}", $header);
            $this->styleHeader($sheet, "{$col}{$row}", 'E5E7EB', '111827');
        }
        $row++;

        foreach ($items as $item) {
            $sheet->setCellValue("A{$row}", (string) ($item['paid_at'] ?? ''));
            $sheet->setCellValue("B{$row}", (string) ($item['category'] ?? ''));
            $sheet->setCellValue("C{$row}", (string) ($item['description'] ?? ''));
            $sheet->setCellValue("D{$row}", (string) ($item['branch'] ?? ''));
            $this->writeMoney($sheet, "E{$row}", (float) ($item['usd'] ?? 0));
            $this->writeMoney($sheet, "F{$row}", (float) ($item['ves'] ?? 0));
            $row++;
        }

        return $row;
    }

    private function addLogo(Worksheet $sheet): void
    {
        $path = public_path('images/logos/farmadoc-ligth.png');
        if (! is_readable($path)) {
            return;
        }

        $sheet->mergeCells('A1:C4');

        foreach ([1, 2, 3, 4] as $logoRow) {
            $sheet->getRowDimension($logoRow)->setRowHeight(24);
        }

        $sheet->getRowDimension(5)->setRowHeight(10);

        $drawing = new Drawing;
        $drawing->setName('Farmadoc');
        $drawing->setDescription('Farmadoc');
        $drawing->setPath($path);
        $drawing->setHeight(110);
        $drawing->setWidth(220);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }

    private function writeMoney(Worksheet $sheet, string $cell, float $amount): void
    {
        $sheet->setCellValueExplicit($cell, round($amount, 2), DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function styleTitle(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(16);
    }

    private function styleHeader(Worksheet $sheet, string $range, string $bg, string $fg): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FF'.$fg);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$bg);
    }

    private function styleSectionHeader(Worksheet $sheet, string $range, string $bg): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$bg);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function styleCardRow(Worksheet $sheet, string $range, string $accent): void
    {
        $sheet->getStyle($range)->getBorders()->getLeft()
            ->setBorderStyle(Border::BORDER_MEDIUM)
            ->getColor()->setARGB('FF'.$accent);
    }

    public function filename(): string
    {
        return 'reporte-financiero-'.now()->format('YmdHis').'.xlsx';
    }
}
