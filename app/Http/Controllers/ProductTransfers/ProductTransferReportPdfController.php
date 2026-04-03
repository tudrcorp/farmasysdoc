<?php

namespace App\Http\Controllers\ProductTransfers;

use App\Http\Controllers\Controller;
use App\Services\Reports\ProductTransferReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProductTransferReportPdfController extends Controller
{
    public function __invoke(Request $request, ProductTransferReportBuilder $builder): Response
    {
        $from = Carbon::parse((string) $request->query('from'))->startOfDay();
        $until = Carbon::parse((string) $request->query('until'))->endOfDay();

        if ($until->lt($from)) {
            abort(422);
        }

        $payload = $builder->build($from, $until, $request->user());

        $suffix = $from->isSameDay($until)
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d').'_'.$until->format('Y-m-d');
        $filename = 'traslados-'.$suffix.'.pdf';

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            $from->toDateString().'|'.$until->toDateString().'|'.($payload['generated_at'] ?? '').'|'.($payload['generated_by'] ?? '')
        ), 0, 10));

        return Pdf::loadView('pdf.product-transfers-report', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
