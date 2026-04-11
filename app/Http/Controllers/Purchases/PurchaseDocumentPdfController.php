<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PurchaseDocumentPdfController extends Controller
{
    public function __invoke(Request $request, Purchase $purchase): Response
    {
        $this->authorizePurchaseAccess($request, $purchase);

        $purchase->load([
            'supplier',
            'branch',
            'items' => fn ($query) => $query->orderBy('line_number')->orderBy('id'),
        ]);

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $pdfLogoDataUri = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $generatedAt = now()->format('d/m/Y H:i');
        $generatedBy = $request->user() instanceof User
            ? ($request->user()->email ?? $request->user()->name ?? 'usuario')
            : 'sistema';

        $updatedAtKey = $purchase->updated_at?->toIso8601String() ?? '';

        $pdfDocumentRef = strtoupper(substr(hash(
            'sha256',
            (string) $purchase->getKey().'|'.$updatedAtKey.'|'.$generatedAt
        ), 0, 10));

        $filename = 'compra-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $purchase->purchase_number).'.pdf';

        return Pdf::loadView('pdf.purchase-document', [
            'purchase' => $purchase,
            'pdf_logo_data_uri' => $pdfLogoDataUri,
            'pdf_document_ref' => $pdfDocumentRef,
            'generated_at' => $generatedAt,
            'generated_by' => $generatedBy,
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function authorizePurchaseAccess(Request $request, Purchase $purchase): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return;
        }

        if ($user->branch_id === null || (int) $purchase->branch_id !== (int) $user->branch_id) {
            abort(403);
        }
    }
}
