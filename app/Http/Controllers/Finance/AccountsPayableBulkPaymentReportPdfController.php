<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountsPayable;
use App\Models\User;
use App\Services\Finance\AccountsPayablePaymentReportPdfFactory;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

final class AccountsPayableBulkPaymentReportPdfController extends Controller
{
    private const MAX_IDS = 100;

    public function __invoke(
        Request $request,
        AccountsPayablePaymentReportPdfFactory $factory,
    ): Response {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isAdministrator() && ! $user->canAccessFarmaadminMenuKey('accounts_payable')) {
            abort(403, 'No tienes permiso para Cuentas por pagar.');
        }

        $ids = $this->parseIds((string) $request->query('ids', ''));
        if ($ids === []) {
            abort(422, 'Debe indicar al menos una cuenta por pagar.');
        }

        if (count($ids) > self::MAX_IDS) {
            abort(422, 'El reporte admite como máximo '.self::MAX_IDS.' registros por descarga.');
        }

        /** @var Collection<int, AccountsPayable> $records */
        $records = BranchAuthScope::apply(
            AccountsPayable::query()
                ->whereKey($ids)
                ->with(['branch', 'purchase']),
        )->get();

        if ($records->count() !== count($ids)) {
            abort(403, 'Uno o más registros no están disponibles o están fuera de su alcance.');
        }

        $notPaid = $records->first(
            static fn (AccountsPayable $record): bool => $record->status !== AccountsPayableStatus::PAGADO,
        );

        if ($notPaid instanceof AccountsPayable) {
            abort(422, 'El reporte masivo solo incluye cuentas por pagar en estado «Pagado».');
        }

        return $factory->downloadMany($records, $user);
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(
                static fn (string $value): int => (int) trim($value),
                explode(',', $raw),
            ),
            static fn (int $id): bool => $id > 0,
        )));

        sort($ids);

        return $ids;
    }
}
