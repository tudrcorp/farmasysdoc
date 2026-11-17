<?php

namespace App\Http\Controllers\Finance;

use App\Filament\Resources\AccountsReceivables\Actions\DownloadAccountsReceivableCollectionNoteAction;
use App\Http\Controllers\Controller;
use App\Models\AccountsReceivable;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsReceivableCollectionNotePdfFactory;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AccountsReceivableCollectionNotePdfController extends Controller
{
    public function __invoke(
        Request $request,
        AccountsReceivable $accountsReceivable,
        AccountsReceivableCollectionNotePdfFactory $factory,
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

        if (! DownloadAccountsReceivableCollectionNoteAction::isAvailableFor($accountsReceivable)) {
            abort(422, 'La nota de cobro no está disponible para ventas anuladas.');
        }

        AuditLogger::forModel($accountsReceivable, 'accounts_receivable_collection_note_generated', [
            'sale_id' => $accountsReceivable->sale_id,
            'sale_number' => $accountsReceivable->sale_number_snapshot,
            'remaining_principal_usd' => $accountsReceivable->remaining_principal_usd,
        ]);

        return $factory->download($accountsReceivable, $user);
    }
}
