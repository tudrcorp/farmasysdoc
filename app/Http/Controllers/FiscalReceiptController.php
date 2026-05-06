<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\ThermalFiscalReceiptFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class FiscalReceiptController extends Controller
{
    public function printDeliveryNote(Request $request, Sale $sale): View
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items.product']);

        AuditLogger::record(
            'pos_caja_delivery_note_viewed',
            'Caja · Vista de nota de entrega · '.$sale->sale_number,
            Sale::class,
            $sale->id,
            $sale->sale_number,
            ['module' => 'pos_caja'],
        );

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $pdfLogoDataUri = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return view('sales.delivery-note-print', [
            'sale' => $sale,
            'saleViewUrl' => SaleResource::getUrl('view', ['record' => $sale]),
            'salesIndexUrl' => SaleResource::getUrl('index'),
            'app_name' => (string) config('app.name'),
            'pdf_logo_data_uri' => $pdfLogoDataUri,
        ]);
    }

    public function print(Request $request, Sale $sale): View
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items']);

        AuditLogger::record(
            'pos_caja_fiscal_receipt_viewed',
            'Caja · Vista de comprobante fiscal · '.$sale->sale_number,
            Sale::class,
            $sale->id,
            $sale->sale_number,
            ['module' => 'pos_caja'],
        );

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $plain = $formatter->format($sale);

        return view('sales.fiscal-receipt-print', [
            'sale' => $sale,
            'plain' => $plain,
            'saleViewUrl' => SaleResource::getUrl('view', ['record' => $sale]),
            'salesIndexUrl' => SaleResource::getUrl('index'),
        ]);
    }

    public function show(Request $request, Sale $sale): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items']);

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $plain = $formatter->format($sale);

        if ($request->query('format') === 'escpos') {
            return response($formatter->wrapEscPos($plain), 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="factura-'.$sale->sale_number.'.bin"',
            ]);
        }

        return response($plain, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="factura-'.$sale->sale_number.'.txt"',
        ]);
    }
}
