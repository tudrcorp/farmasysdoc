<?php

namespace App\Services\Fiscal;

use App\Support\Fiscal\SalesBookLayout;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Libro de Ventas en Excel (.xlsx) con el formato de columnas de la empresa y el resumen de la Forma 30.
 */
final class SalesBookXlsxExporter
{
    private const MONEY_FORMAT = '#,##0.00;-#,##0.00';

    /**
     * @param  array<string, mixed>  $book  Resultado de {@see SalesBookBuilder::build()} (o el snapshot de un libro cerrado).
     * @param  string  $statusLine  Línea de estado (borrador o datos del cierre y huella).
     * @return string Ruta del archivo temporal generado.
     */
    public function export(array $book, string $statusLine): string
    {
        $path = tempnam(sys_get_temp_dir(), 'libro-ventas-').'.xlsx';
        $columns = SalesBookLayout::columns();
        $lastColumn = count($columns) - 1;
        $header = $book['header'];

        $options = new Options;
        $options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::Legal, fitToHeight: 0, fitToWidth: 1));

        foreach ($columns as $index => $column) {
            $options->setColumnWidth($column['width'], $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Libro de Ventas');

        $rowNumber = 0;
        $add = function (Row $row) use ($writer, &$rowNumber): int {
            $writer->addRow($row);

            return ++$rowNumber;
        };

        $title = (new Style)->setFontBold()->setFontSize(11);
        $bold = (new Style)->setFontBold();

        $line = $add(Row::fromValues(['NOMBRE O RAZÓN SOCIAL: '.$header['company_name'].'   SUC: '.$header['branch_name'].' — '.$header['branch_address']], $title));
        $options->mergeCells(0, $line, $lastColumn - 3, $line);

        $add(Row::fromValues(['RIF: '.$header['company_rif']], $bold));
        $add(Row::fromValues(['LIBRO DE VENTAS CORRESPONDIENTE AL MES DE: '.$header['month_name'].'   AÑO: '.$header['year'].'   ('.$header['half_label'].')'], $title));
        $add(Row::fromValues(['PERÍODO: '.$header['range_label']], $bold));
        $add(Row::fromValues([$statusLine], (new Style)->setFontItalic()->setFontColor('7F1D1D')));
        $add(Row::fromValues([]));

        $headerStyle = $this->bordered((new Style)
            ->setFontBold()
            ->setFontSize(8)
            ->setShouldWrapText()
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBackgroundColor('E5E7EB'));

        $groupCells = array_fill(0, count($columns), '');
        foreach (SalesBookLayout::groups() as [$from, $to, $text]) {
            $groupCells[$from] = $text;
        }
        $groupLine = $add(Row::fromValues($groupCells, $headerStyle));
        foreach (SalesBookLayout::groups() as [$from, $to]) {
            $options->mergeCells($from, $groupLine, $to, $groupLine);
        }

        $headerRow = Row::fromValues(array_map(fn (array $column): string => $column['label'], $columns), $headerStyle);
        $headerRow->setHeight(54);
        $add($headerRow);

        $textStyle = $this->bordered((new Style)->setFontSize(8)->setShouldWrapText());
        $moneyStyle = $this->bordered((new Style)->setFontSize(8)->setFormat(self::MONEY_FORMAT));

        foreach ($book['rows'] as $row) {
            $cells = [];

            foreach ($columns as $column) {
                $value = SalesBookLayout::value($row, $column['key'], $header);
                $cells[] = Cell::fromValue($value, ($column['money'] ?? false) ? $moneyStyle : $textStyle);
            }

            $add(new Row($cells));
        }

        $totalText = $this->bordered((new Style)->setFontBold()->setFontSize(8)->setBackgroundColor('F3F4F6'));
        $totalMoney = $this->bordered((new Style)->setFontBold()->setFontSize(8)->setFormat(self::MONEY_FORMAT)->setBackgroundColor('F3F4F6'));
        $totalCells = [];

        foreach ($columns as $index => $column) {
            if ($column['money'] ?? false) {
                $totalCells[] = Cell::fromValue((float) $book['totals'][$column['key']], $totalMoney);
            } else {
                $totalCells[] = Cell::fromValue($index === 6 ? 'TOTALES (BOLÍVARES)' : '', $totalText);
            }
        }

        $add(new Row($totalCells));
        $add(Row::fromValues([]));
        $add(Row::fromValues([]));

        $add(Row::fromValues(['RESUMEN DE VENTAS Y DÉBITOS FISCALES — '.$header['month_name'].' '.$header['year'].' ('.$header['half_label'].')'], $title));

        $summaryHeader = $this->bordered((new Style)->setFontBold()->setFontSize(9)->setBackgroundColor('E5E7EB')->setCellAlignment(CellAlignment::CENTER));
        $add(Row::fromValues(['DÉBITOS FISCALES', '', '', '', '', '', 'Ítem', 'Base imponible', 'Ítem', 'Débito fiscal', 'Ítem', 'IVA retenido por el comprador'], $summaryHeader));

        $summaryText = $this->bordered((new Style)->setFontSize(9));
        $summaryMoney = $this->bordered((new Style)->setFontSize(9)->setFormat(self::MONEY_FORMAT));

        foreach (SalesBookLayout::summaryLines() as [$label, $baseItem, $baseKey, $taxItem, $taxKey, $retItem, $retKey]) {
            $summaryLine = $add(new Row([
                Cell::fromValue($label, $summaryText),
                Cell::fromValue('', $summaryText),
                Cell::fromValue('', $summaryText),
                Cell::fromValue('', $summaryText),
                Cell::fromValue('', $summaryText),
                Cell::fromValue('', $summaryText),
                Cell::fromValue($baseItem !== '' ? 'Ítem '.$baseItem : '', $summaryText),
                Cell::fromValue($baseKey !== null ? (float) $book['summary'][$baseKey] : '', $summaryMoney),
                Cell::fromValue($taxItem !== '' ? 'Ítem '.$taxItem : '', $summaryText),
                Cell::fromValue($taxKey !== null ? (float) $book['summary'][$taxKey] : '', $summaryMoney),
                Cell::fromValue($retItem !== '' ? 'Ítem '.$retItem : '', $summaryText),
                Cell::fromValue($retKey !== null ? (float) $book['summary'][$retKey] : '', $summaryMoney),
            ]));
            $options->mergeCells(0, $summaryLine, 5, $summaryLine);
        }

        $add(Row::fromValues([]));
        $add(Row::fromValues(['Generado por Farmadoc el '.$book['generated_at'].' por '.$book['generated_by'].'. Montos en bolívares (Bs).'], (new Style)->setFontSize(8)->setFontItalic()));

        $writer->close();

        return $path;
    }

    private function bordered(Style $style): Style
    {
        $part = fn (string $name): BorderPart => new BorderPart($name, '9CA3AF', Border::WIDTH_THIN);

        return $style->setBorder(new Border($part(Border::TOP), $part(Border::BOTTOM), $part(Border::LEFT), $part(Border::RIGHT)));
    }
}
