<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\ThermalFiscalReceiptFormatter;
use App\Services\Fiscal\ThermalFiscalReceiptImageGenerator;
use App\Support\Notifications\WhatsAppLink;
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

        return view('sales.fiscal-receipt-print', array_merge([
            'sale' => $sale,
            'plain' => $plain,
            'saleViewUrl' => SaleResource::getUrl('view', ['record' => $sale]),
            'salesIndexUrl' => SaleResource::getUrl('index'),
        ], $this->whatsappShareViewData(
            $sale,
            'sales.fiscal-receipt.whatsapp-image',
            'factura',
        )));
    }

    public function fiscalReceiptWhatsappImage(Request $request, Sale $sale): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items']);

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $jpeg = app(ThermalFiscalReceiptImageGenerator::class)->generateJpeg(
            $formatter->format($sale),
        );

        return $this->jpegResponse($jpeg, 'factura-'.$sale->sale_number.'.jpg');
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

    public function printCreditNote(Request $request, Sale $sale): View
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items']);

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $plain = $formatter->formatCreditNote($sale);

        AuditLogger::record(
            'sale_credit_note_viewed',
            'Ventas · Vista de nota de crédito · '.$sale->sale_number,
            Sale::class,
            $sale->id,
            $sale->sale_number,
            ['module' => 'sales'],
        );

        return view('sales.fiscal-receipt-print', array_merge([
            'sale' => $sale,
            'plain' => $plain,
            'documentTitle' => 'Nota de crédito',
            'saleViewUrl' => SaleResource::getUrl('view', ['record' => $sale]),
            'salesIndexUrl' => SaleResource::getUrl('index'),
        ], $this->whatsappShareViewData(
            $sale,
            'sales.credit-note.whatsapp-image',
            'nota-credito',
        )));
    }

    public function creditNoteWhatsappImage(Request $request, Sale $sale): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items']);

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $jpeg = app(ThermalFiscalReceiptImageGenerator::class)->generateJpeg(
            $formatter->formatCreditNote($sale),
        );

        return $this->jpegResponse($jpeg, 'nota-credito-'.$sale->sale_number.'.jpg');
    }

    public function showCreditNote(Request $request, Sale $sale): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless(SaleResource::canView($sale), 403);

        $sale->load(['branch', 'client', 'items']);

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $plain = $formatter->formatCreditNote($sale);

        if ($request->query('format') === 'escpos') {
            return response($formatter->wrapEscPos($plain), 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="nota-credito-'.$sale->sale_number.'.bin"',
            ]);
        }

        return response($plain, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="nota-credito-'.$sale->sale_number.'.txt"',
        ]);
    }

    /**
     * @return array{
     *     whatsappPhoneDigits: ?string,
     *     whatsappImageUrl: ?string,
     *     whatsappImageFilename: ?string
     * }
     */
    private function whatsappShareViewData(Sale $sale, string $imageRouteName, string $downloadBaseName): array
    {
        $phoneDigits = WhatsAppLink::normalizePhoneDigits($sale->client?->phone);

        if ($phoneDigits === null) {
            return [
                'whatsappPhoneDigits' => null,
                'whatsappImageUrl' => null,
                'whatsappImageFilename' => null,
            ];
        }

        return [
            'whatsappPhoneDigits' => $phoneDigits,
            'whatsappImageUrl' => route($imageRouteName, $sale),
            'whatsappImageFilename' => $downloadBaseName.'-'.$sale->sale_number.'.jpg',
        ];
    }

    private function jpegResponse(string $jpeg, string $filename): Response
    {
        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
