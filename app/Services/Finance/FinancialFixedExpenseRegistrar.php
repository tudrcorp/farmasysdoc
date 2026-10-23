<?php

namespace App\Services\Finance;

use App\Enums\FinancialFixedExpenseCategory;
use App\Enums\PettyCashCurrency;
use App\Models\FinancialFixedExpense;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

final class FinancialFixedExpenseRegistrar
{
    public function __construct(
        private PettyCashAmountResolver $amountResolver,
    ) {}

    /**
     * @param  array{
     *     branch_id?: int|null,
     *     paid_at: CarbonInterface|string,
     *     category: FinancialFixedExpenseCategory|string,
     *     description: string,
     *     currency: PettyCashCurrency|string,
     *     amount: float|int|string,
     *     receipt_path?: string|null,
     * }  $payload
     */
    public function register(User $actor, array $payload): FinancialFixedExpense
    {
        if (! $actor->isAdministrator() && ! $actor->hasGerenciaRole()) {
            throw new RuntimeException('No tiene permiso para registrar gastos fijos.');
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            throw new InvalidArgumentException('Describa el gasto fijo.');
        }

        $category = $payload['category'] instanceof FinancialFixedExpenseCategory
            ? $payload['category']
            : FinancialFixedExpenseCategory::tryFrom((string) ($payload['category'] ?? ''));
        if (! $category instanceof FinancialFixedExpenseCategory) {
            throw new InvalidArgumentException('Seleccione una categoría válida.');
        }

        $currency = $payload['currency'] instanceof PettyCashCurrency
            ? $payload['currency']
            : PettyCashCurrency::tryFrom((string) ($payload['currency'] ?? ''));
        if (! $currency instanceof PettyCashCurrency) {
            throw new InvalidArgumentException('Indique si el pago es en dólares o en bolívares.');
        }

        $paidAt = $payload['paid_at'] instanceof CarbonInterface
            ? $payload['paid_at']
            : Carbon::parse((string) $payload['paid_at']);

        $amount = round((float) $payload['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        $converted = $this->amountResolver->resolve($currency, $amount, $paidAt);

        $branchId = isset($payload['branch_id']) && filled($payload['branch_id'])
            ? (int) $payload['branch_id']
            : null;

        if (! $actor->isAdministrator() && ! $actor->isDeliveryUser() && $branchId !== null) {
            $allowed = $actor->restrictedBranchIdsForQueries();
            if ($allowed !== [] && ! in_array($branchId, $allowed, true)) {
                throw new InvalidArgumentException('No puede registrar gastos en esa sucursal.');
            }
        }

        return FinancialFixedExpense::query()->create([
            'branch_id' => $branchId,
            'paid_at' => $paidAt,
            'category' => $category,
            'description' => $description,
            'currency' => $currency,
            'amount' => $amount,
            'amount_usd' => $converted['amount_usd'],
            'amount_ves' => $converted['amount_ves'],
            'bcv_ves_per_usd' => $converted['bcv_ves_per_usd'],
            'receipt_path' => filled($payload['receipt_path'] ?? null)
                ? trim((string) $payload['receipt_path'])
                : null,
            'registered_by' => $actor->id,
        ]);
    }
}
