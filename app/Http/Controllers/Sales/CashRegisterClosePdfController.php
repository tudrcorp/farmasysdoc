<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\CashRegisterCloseReportBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CashRegisterClosePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $from = Carbon::parse((string) $request->query('from'))->startOfDay();
        $until = Carbon::parse((string) $request->query('until'))->endOfDay();

        if ($until->lt($from)) {
            abort(422);
        }

        $payload = app(CashRegisterCloseReportBuilder::class)->build($from, $until);

        $suffix = $from->isSameDay($until)
            ? $from->format('Y-m-d')
            : $from->format('Y-m-d').'_'.$until->format('Y-m-d');
        $filename = 'cierre-caja-'.$suffix.'.pdf';

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            $from->toDateString().'|'.$until->toDateString().'|'.($payload['generated_at'] ?? '').'|'.($payload['generated_by'] ?? '')
        ), 0, 10));

        return Pdf::loadView('pdf.cash-register-close', $payload)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
