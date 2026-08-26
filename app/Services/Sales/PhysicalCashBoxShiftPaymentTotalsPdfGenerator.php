<?php

namespace App\Services\Sales;

use Barryvdh\DomPDF\Facade\Pdf;

final class PhysicalCashBoxShiftPaymentTotalsPdfGenerator
{
    /**
     * @param  array{
     *     cashier_name: string,
     *     branch_name: string,
     *     opened_at_label: string,
     *     closed_at_label: string,
     *     payment_breakdown: list<array{
     *         method: string,
     *         label: string,
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     }>,
     *     payment_breakdown_totals: array{
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     },
     *     close_detail: array{
     *         sale_count: int,
     *         total_usd: float,
     *         total_ves: float,
     *         punto_venta_ves: float,
     *         pos_terminals: list<array{id: int|null, label: string, amount_ves: float}>,
     *         pago_movil_ves: float,
     *         transfer_ves: float,
     *         transfer_usd: float,
     *         efectivo_ves: float,
     *         efectivo_usd: float,
     *         usd_methods_total: float,
     *         ves_methods_total: float,
     *     },
     * }  $report
     */
    public function generate(array $report): string
    {
        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $pdfLogoDataUri = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return (string) Pdf::loadView('pdf.physical-cash-box-payment-totals', [
            'app_name' => (string) config('app.name'),
            'branch_name' => $report['branch_name'],
            'cashier_name' => $report['cashier_name'],
            'opened_at_label' => $report['opened_at_label'],
            'closed_at_label' => $report['closed_at_label'],
            'close_detail' => $report['close_detail'],
            'payment_breakdown' => $report['payment_breakdown'],
            'payment_breakdown_totals' => $report['payment_breakdown_totals'],
            'cachea_detail' => $report['cachea_detail'] ?? null,
            'pdf_logo_data_uri' => $pdfLogoDataUri,
            'generated_at' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i:s'),
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
