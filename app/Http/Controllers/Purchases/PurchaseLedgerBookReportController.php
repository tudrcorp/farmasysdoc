<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\PurchaseLedgerBookReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PurchaseLedgerBookReportController extends Controller
{
    public function __invoke(Request $request, PurchaseLedgerBookReportBuilder $builder): Response
    {
        $this->authorizeAccess($request);

        $taxPeriod = (string) $request->query('tax_period', '');
        $half = (string) $request->query('half', PurchaseLedgerBookReportBuilder::HALF_MONTH);
        $format = (string) $request->query('format', 'pdf');

        $user = $request->user();
        $actor = $user instanceof User
            ? (filled($user->email) ? (string) $user->email : (string) ($user->name ?? 'usuario'))
            : 'sistema';

        try {
            $payload = $builder->build($taxPeriod, $half, $actor);
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        AuditLogger::record(
            event: 'purchase_ledger_book_report_downloaded',
            description: 'Libro de Compras: descarga de reporte SENIAT.',
            properties: [
                'tax_period' => $taxPeriod,
                'half' => $half,
                'format' => $format,
                'rows' => count($payload['rows']),
            ],
        );

        $suffix = str_replace('/', '-', $taxPeriod).'-'.$half;
        $filenameBase = 'libro-de-compras-'.$suffix;

        if ($format === 'csv') {
            return $this->streamCsv($payload, $filenameBase.'.csv');
        }

        return Pdf::loadView('pdf.purchase-ledger-book', $payload)
            ->setPaper('legal', 'landscape')
            ->download($filenameBase.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function streamCsv(array $payload, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($payload): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'NOMBRE DE LA PERSONA NATURAL Y/O RAZON SOCIAL',
                $payload['company_name'],
                'RIF',
                $payload['company_rif'],
            ], ';');
            fputcsv($stream, [
                'LIBRO DE COMPRAS CORRESPONDIENTES AL MES',
                $payload['period_title'],
                $payload['month_label'],
                'AÑO',
                $payload['year'],
            ], ';');
            fputcsv($stream, [], ';');
            fputcsv($stream, [
                'NUMERO DE OPERACIONES',
                'EMISION FACTURAS, ND Y NC, TICKETS FISCALES',
                'TIPO DE DOCUMENTO',
                'FACTURA O DOCUMENTO EQUIVALENTE',
                'NUMERO DE CONTROL',
                'SERIE',
                'NOMBRE COMPLETO / RAZON SOCIAL',
                'NUMERO DE RIF O CEDULA',
                'TIPO DE CONTRIBUYENTE',
                'TOTAL GRAVADAS INCLUYENDO IVA Y EXENTAS',
                'EXENTAS EXONERADAS O NO SUJETAS',
                'MONTO EXPORTACION',
                'BASE IMPONIBLE',
                'IVA',
                'BASE IMPONIBLE REDUCIDA',
                'IVA REDUCIDA',
                'ALICUOTA',
                'FECHA DE EMISION DEL COMPROBANTE DE RETENCION',
                'NUMERO DEL COMPROBANTE DE RETENCION',
                'MONTO DE RETENCION',
            ], ';');

            foreach ($payload['rows'] as $row) {
                fputcsv($stream, [
                    $row['operation_number'],
                    $row['invoice_date'],
                    $row['document_type'],
                    $row['document_number'],
                    $row['control_number'],
                    $row['serie'],
                    $row['supplier_name'],
                    $row['supplier_rif'],
                    $row['taxpayer_type'],
                    $this->csvNumber($row['total_with_vat']),
                    $this->csvNumber($row['exempt']),
                    $this->csvNumber($row['export']),
                    $this->csvNumber($row['taxable_base']),
                    $this->csvNumber($row['tax_caused']),
                    $this->csvNumber($row['taxable_base_reduced']),
                    $this->csvNumber($row['tax_reduced']),
                    $row['vat_rate_percent'] !== null ? number_format((float) $row['vat_rate_percent'], 0, ',', '.').'%' : '',
                    $row['retention_issued_at'] ?? '',
                    $row['retention_voucher_number'] ?? '',
                    $this->csvNumber($row['retention_amount']),
                ], ';');
            }

            fputcsv($stream, [], ';');
            $totals = $payload['totals'];
            fputcsv($stream, ['Total Compras Internas gravadas por Alicuota General 16%', 'Item 42', $this->csvNumber($totals['taxable_base_16']), 'Item 43', $this->csvNumber($totals['tax_caused_16']), 'Item 66', $this->csvNumber($totals['retention'])], ';');
            fputcsv($stream, ['Total Importaciones Gravadas por Alicuota Reducida', 'Item 333', '0,00', 'Item 43', '0,00'], ';');
            fputcsv($stream, ['Total Compras Internas gravadas por Alicuota General 8%', 'Item 42', '0,00', 'Item 43', '0,00'], ';');
            fputcsv($stream, ['Total Compras Internas gravadas por Alicuota General 9%', 'Item 442', '', 'Item 452'], ';');
            fputcsv($stream, ['Total Compras Internas gravadas por Alicuota General mas Adicional', 'Item 443', '', 'Item 453'], ';');
            fputcsv($stream, ['Total Compras y Creditos Fiscales para efectos de determinacion:', 'Item 46', $this->csvNumber($totals['total_with_vat']), '', $this->csvNumber($totals['tax_caused_16']), '', $this->csvNumber($totals['retention'])], ';');

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function csvNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdministrator() || $user->canAccessFarmaadminMenuKey('purchase_ledgers')) {
            return;
        }

        abort(403, 'No tienes permiso para el Libro de Compras.');
    }
}
