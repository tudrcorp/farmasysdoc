<?php

namespace App\Services\Finance;

use App\Enums\PettyCashCurrency;
use App\Models\PettyCashExpense;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PettyCashExpenseCsvExporter
{
    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'Fecha del gasto',
            'Sucursal',
            'Solicitud',
            'Descripción',
            'Moneda',
            'Monto ingresado',
            'USD',
            'VES',
            'Tasa BCV',
            'Registrado por',
            'Comprobante',
        ];
    }

    /**
     * @param  Builder<PettyCashExpense>  $query
     */
    public function stream(Builder $query, ?string $filename = null): StreamedResponse
    {
        $fileName = $filename ?? ('egresos-caja-chica-'.now()->format('Y-m-d-H-i-s').'.csv');

        $expenses = (clone $query)
            ->with(['fund.branch:id,name', 'registeredBy:id,name'])
            ->orderBy('spent_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalUsd = 0.0;
        $totalVes = 0.0;

        foreach ($expenses as $record) {
            if (! $record instanceof PettyCashExpense) {
                continue;
            }

            $totalUsd += (float) $record->amount_usd;
            $totalVes += (float) $record->amount_ves;

            $currency = $record->currency instanceof PettyCashCurrency
                ? $record->currency->shortLabel()
                : (string) $record->currency;

            $rows[] = [
                $record->spent_at?->format('d/m/Y H:i') ?? '',
                (string) ($record->fund?->branch?->name ?? ''),
                $record->petty_cash_fund_id !== null ? '#'.$record->petty_cash_fund_id : '',
                (string) $record->description,
                $currency,
                number_format((float) $record->amount, 2, ',', '.'),
                number_format((float) $record->amount_usd, 2, ',', '.'),
                number_format((float) $record->amount_ves, 2, ',', '.'),
                $record->bcv_ves_per_usd !== null
                    ? number_format((float) $record->bcv_ves_per_usd, 6, ',', '.')
                    : '',
                (string) ($record->registeredBy?->name ?? ''),
                (string) $record->receipt_path,
            ];
        }

        $rows[] = [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            number_format($totalUsd, 2, ',', '.'),
            number_format($totalVes, 2, ',', '.'),
            '',
            '',
            '',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $this->headers(), ';');
            foreach ($rows as $row) {
                fputcsv($stream, $row, ';');
            }
            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Builder<PettyCashExpense>
     */
    public function queryForUser(User $user, CarbonInterface $from, CarbonInterface $to): Builder
    {
        $query = PettyCashExpense::query()
            ->whereBetween('spent_at', [$from, $to]);

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        $branchIds = $user->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'fund',
            fn (Builder $q): Builder => $q->whereIn('branch_id', $branchIds),
        );
    }
}
