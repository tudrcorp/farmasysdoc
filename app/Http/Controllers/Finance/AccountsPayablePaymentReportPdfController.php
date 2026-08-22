<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\User;
use App\Services\Finance\AccountsPayablePaymentReportPdfFactory;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AccountsPayablePaymentReportPdfController extends Controller
{
    public function __invoke(
        Request $request,
        AccountsPayable $accountsPayable,
        AccountsPayablePaymentReportPdfFactory $factory,
    ): Response {
        $this->authorizeAccess($request, $accountsPayable);

        if ($accountsPayable->status !== AccountsPayableStatus::PAGADO) {
            abort(422, 'El reporte detallado solo está disponible para cuentas por pagar en estado «Pagado».');
        }

        $actor = $request->user() instanceof User ? $request->user() : null;

        return $factory->download($accountsPayable, $actor);
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
