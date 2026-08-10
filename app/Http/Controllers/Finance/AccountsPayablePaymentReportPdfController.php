<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayablePaymentReportBuilder;
use App\Support\Finance\AccountsPayableStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AccountsPayablePaymentReportPdfController extends Controller
{
    public function __invoke(
        Request $request,
        AccountsPayable $accountsPayable,
        AccountsPayablePaymentReportBuilder $builder,
    ): Response {
        $this->authorizeAccess($request, $accountsPayable);

        if ($accountsPayable->status !== AccountsPayableStatus::PAGADO) {
            abort(422, 'El reporte detallado solo está disponible para cuentas por pagar en estado «Pagado».');
        }

        $payload = $builder->build($accountsPayable, $request->user() instanceof User ? $request->user() : null);

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        $invoice = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            (string) ($accountsPayable->supplier_invoice_number ?: $accountsPayable->getKey()),
        ) ?: 'cxp';

        $payload['pdf_document_ref'] = strtoupper(substr(hash(
            'sha256',
            (string) $accountsPayable->getKey().'|'.($accountsPayable->paid_at?->toIso8601String() ?? '').'|'.($payload['generated_at'] ?? '')
        ), 0, 10));

        $filename = 'reporte-pago-cxp-'.$invoice.'.pdf';

        return Pdf::loadView('pdf.accounts-payable-payment-report', $payload)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function authorizeAccess(Request $request, AccountsPayable $accountsPayable): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isAdministrator() && ! $user->canAccessFarmaadminMenuKey('accounts_payable')) {
            abort(403, 'No tienes permiso para Cuentas por pagar.');
        }

        $allowed = BranchAuthScope::apply(
            AccountsPayable::query()->whereKey($accountsPayable->getKey()),
        )->exists();

        if (! $allowed) {
            abort(403);
        }
    }
}
