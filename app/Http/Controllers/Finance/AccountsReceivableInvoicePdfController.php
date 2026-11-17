<?php

namespace App\Http\Controllers\Finance;

use App\Filament\Resources\AccountsReceivables\Actions\DownloadAccountsReceivableInvoiceAction;
use App\Http\Controllers\Controller;
use App\Models\AccountsReceivable;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsReceivableInvoicePdfFactory;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AccountsReceivableInvoicePdfController extends Controller
{
    public function __invoke(
        Request $request,
        AccountsReceivable $accountsReceivable,
        AccountsReceivableInvoicePdfFactory $factory,
    ): Response {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isAdministrator() && ! $user->canAccessFarmaadminMenuKey('accounts_receivable')) {
            abort(403, 'No tienes permiso para Cuentas por cobrar.');
        }

        $allowed = BranchAuthScope::apply(
            AccountsReceivable::query()->whereKey($accountsReceivable->getKey()),
        )->exists();

        if (! $allowed) {
            abort(403);
        }

        if (! DownloadAccountsReceivableInvoiceAction::isAvailableFor($accountsReceivable)) {
            abort(422, 'La factura comercial no está disponible para ventas anuladas.');
        }

        AuditLogger::forModel($accountsReceivable, 'accounts_receivable_invoice_generated', [
            'sale_id' => $accountsReceivable->sale_id,
            'sale_number' => $accountsReceivable->sale_number_snapshot,
            'sale_total_usd' => $accountsReceivable->sale_total_usd,
            'remaining_principal_usd' => $accountsReceivable->remaining_principal_usd,
        ]);

        return $factory->download($accountsReceivable, $user);
    }
}
