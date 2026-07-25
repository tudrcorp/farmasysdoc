<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Finance\PurchaseHistoryRetentionVoucherSynchronizer;
use App\Support\Purchases\PurchaseBookRetentionVoucherBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

final class PurchaseBookRetentionVoucherPdfController extends Controller
{
    public function __invoke(
        Request $request,
        PurchaseBookRetentionVoucherBuilder $builder,
        PurchaseHistoryRetentionVoucherSynchronizer $historyRetentionSynchronizer,
    ): Response {
        $this->authorizeAccess($request);

        $supplierRif = trim((string) $request->query('supplier_rif', ''));
        $invoiceDate = trim((string) $request->query('invoice_date', ''));

        if ($supplierRif === '' || $invoiceDate === '') {
            abort(422, 'Debe indicar proveedor y fecha de factura.');
        }

        try {
            $invoiceDate = Carbon::parse($invoiceDate)->toDateString();
        } catch (\Throwable) {
            abort(422, 'Fecha de factura inválida.');
        }

        $historyRetentionSynchronizer->markIssuedOnPrint($supplierRif, $invoiceDate);

        $payload = $builder->build($supplierRif, $invoiceDate);

        $filename = sprintf(
            'comprobante-retencion-iva-%s-%s.pdf',
            preg_replace('/[^A-Za-z0-9_-]+/', '-', $supplierRif) ?: 'proveedor',
            $invoiceDate,
        );

        return Pdf::loadView('pdf.purchase-book-retention-voucher', $payload)
            ->setPaper('letter', 'landscape')
            ->download($filename);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdministrator() || $user->canAccessFarmaadminMenuKey('purchase_books')) {
            return;
        }

        abort(403, 'No tienes permiso para Retenciones.');
    }
}
