<?php

namespace App\Services\Fiscal;

use App\Support\Fiscal\SalesBookLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Libro de Ventas en PDF (oficio horizontal), paginado con «VAN…/VIENEN…» como el formato de la empresa.
 */
final class SalesBookPdfFactory
{
    private const ROWS_PER_PAGE = 30;

    /**
     * @param  array<string, mixed>  $book
     */
    public function make(array $book, string $statusLine, bool $isDraft): DomPdf
    {
        $moneyKeys = array_values(array_map(
            fn (array $column): string => $column['key'],
            array_filter(SalesBookLayout::columns(), fn (array $column): bool => $column['money'] ?? false),
        ));

        $pages = [];
        $carried = array_fill_keys($moneyKeys, 0);
        $chunks = array_chunk($book['rows'], self::ROWS_PER_PAGE);

        foreach ($chunks === [] ? [[]] : $chunks as $rows) {
            $broughtForward = $carried;

            foreach ($rows as $row) {
                foreach ($moneyKeys as $key) {
                    $carried[$key] += (int) round((float) $row['amounts'][$key] * 100);
                }
            }

            $pages[] = [
                'rows' => $rows,
                'brought_forward' => array_map(fn (int $cents): float => $cents / 100, $broughtForward),
                'carried_forward' => array_map(fn (int $cents): float => $cents / 100, $carried),
            ];
        }

        return Pdf::loadView('pdf.sales-book', [
            'book' => $book,
            'header' => $book['header'],
            'columns' => SalesBookLayout::columns(),
            'groups' => SalesBookLayout::groups(),
            'pages' => $pages,
            'statusLine' => $statusLine,
            'isDraft' => $isDraft,
        ])->setPaper('legal', 'landscape');
    }
}
