<?php

namespace App\Http\Controllers;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Models\User;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pantalla del cajero mientras el agente local imprime la factura (o nota de crédito) en la máquina fiscal.
 */
final class FiscalPrintStatusController extends Controller
{
    public function show(Request $request, Sale $sale): View
    {
        abort_unless(Auth::check() && SaleResource::canView($sale), 403);

        $document = $this->document($request, $sale);

        return view('sales.fiscal-print-status', [
            'sale' => $sale,
            'document' => $document,
            'statusUrl' => route('sales.fiscal-print.status', [$sale, 'type' => $document->type->value]),
            'retryUrl' => route('sales.fiscal-print.retry', [$sale, 'type' => $document->type->value]),
            'nonFiscalUrl' => $document->type === FiscalDocumentType::CreditNote
                ? route('sales.credit-note.print', $sale)
                : route('sales.fiscal-receipt.print', $sale),
            'saleViewUrl' => SaleResource::getUrl('view', ['record' => $sale]),
            'salesIndexUrl' => SaleResource::getUrl('index'),
            'initialState' => $this->state($document),
            'continueAfterSeconds' => max(3, (int) config('fiscal.printers.continue_after_seconds', 8)),
        ]);
    }

    public function status(Request $request, Sale $sale): JsonResponse
    {
        abort_unless(Auth::check() && SaleResource::canView($sale), 403);

        return response()->json($this->state($this->document($request, $sale)));
    }

    public function retry(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless(Auth::check() && SaleResource::canView($sale), 403);

        $document = $this->document($request, $sale);
        $user = Auth::user();

        try {
            app(FiscalDocumentRegistrar::class)->retry(
                $document,
                $user instanceof User ? ($user->email ?? $user->name) : 'sistema',
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('sales.fiscal-print.show', [$sale, 'type' => $document->type->value])
                ->withErrors($e->errors());
        }

        return redirect()->route('sales.fiscal-print.show', [$sale, 'type' => $document->type->value]);
    }

    private function document(Request $request, Sale $sale): FiscalDocument
    {
        $type = FiscalDocumentType::tryFrom((string) $request->query('type', FiscalDocumentType::Invoice->value))
            ?? FiscalDocumentType::Invoice;

        return FiscalDocument::query()
            ->with('fiscalPrinter')
            ->where('sale_id', $sale->id)
            ->where('type', $type)
            ->firstOrFail();
    }

    /**
     * @return array{status: string, label: string, is_final: bool, can_retry: bool, fiscal_number: ?string, error: ?string, printer: ?string, printer_online: bool}
     */
    private function state(FiscalDocument $document): array
    {
        return [
            'status' => $document->status->value,
            'label' => $document->status->label(),
            'is_final' => $document->status->isFinal()
                || in_array($document->status, [FiscalDocumentStatus::Failed, FiscalDocumentStatus::NeedsReview], true),
            'can_retry' => $document->status === FiscalDocumentStatus::Failed,
            'fiscal_number' => $document->fiscal_number,
            'error' => $document->error_message,
            'printer' => $document->fiscalPrinter?->name,
            'printer_online' => (bool) $document->fiscalPrinter?->isOnline(),
        ];
    }
}
