<?php

namespace App\Services\Finance;

use App\Enums\EmployeeBankAccountType;
use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PettyCashFundCsvExporter
{
    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'Fecha de solicitud',
            'Sucursal',
            'Gerente',
            'Estatus',
            'Monto USD',
            'Monto VES',
            'Disponible USD',
            'Disponible VES',
            'Gastado USD',
            'Gastado VES',
            'Motivo',
            'Banco',
            'Tipo de cuenta',
            'Titular',
            'Cédula',
            'Teléfono',
            'Número de cuenta',
            'Referencia',
            'Aprobado por',
            'Fecha de decisión',
        ];
    }

    /**
     * @param  Builder<PettyCashFund>  $query
     */
    public function stream(Builder $query, ?string $filename = null): StreamedResponse
    {
        $fileName = $filename ?? ('caja-chica-'.now()->format('Y-m-d-H-i-s').'.csv');

        $funds = (clone $query)
            ->with(['branch:id,name', 'requestedBy:id,name', 'approvedBy:id,name'])
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalUsd = 0.0;
        $totalVes = 0.0;
        $remainingUsd = 0.0;
        $remainingVes = 0.0;
        $spentUsd = 0.0;
        $spentVes = 0.0;

        foreach ($funds as $record) {
            if (! $record instanceof PettyCashFund) {
                continue;
            }

            $fundUsd = (float) $record->amount_usd;
            $fundVes = (float) $record->amount_ves;
            $leftUsd = (float) $record->remaining_usd;
            $leftVes = (float) $record->remaining_ves;
            $usedUsd = max(0, round($fundUsd - $leftUsd, 2));
            $usedVes = max(0, round($fundVes - $leftVes, 2));

            $totalUsd += $fundUsd;
            $totalVes += $fundVes;
            $remainingUsd += $leftUsd;
            $remainingVes += $leftVes;
            $spentUsd += $usedUsd;
            $spentVes += $usedVes;

            $status = $record->status instanceof PettyCashFundStatus
                ? $record->status->label()
                : (PettyCashFundStatus::tryFrom((string) $record->status)?->label() ?? (string) $record->status);

            $accountType = $record->bank_account_type instanceof EmployeeBankAccountType
                ? $record->bank_account_type->label()
                : (EmployeeBankAccountType::tryFrom((string) $record->bank_account_type)?->label() ?? '');

            $rows[] = [
                $record->requested_at?->format('d/m/Y') ?? '',
                (string) ($record->branch?->name ?? ''),
                (string) ($record->requestedBy?->name ?? ''),
                $status,
                number_format($fundUsd, 2, ',', '.'),
                number_format($fundVes, 2, ',', '.'),
                number_format($leftUsd, 2, ',', '.'),
                number_format($leftVes, 2, ',', '.'),
                number_format($usedUsd, 2, ',', '.'),
                number_format($usedVes, 2, ',', '.'),
                (string) $record->notes,
                $record->bankLabel(),
                $accountType,
                (string) ($record->bank_account_holder ?? ''),
                (string) ($record->holder_national_id ?? ''),
                (string) ($record->holder_phone ?? ''),
                (string) ($record->bank_account_number ?? ''),
                (string) ($record->payment_reference ?? ''),
                (string) ($record->approvedBy?->name ?? ''),
                $record->approved_at?->format('d/m/Y H:i') ?? '',
            ];
        }

        $rows[] = [
            'TOTAL',
            '',
            '',
            '',
            number_format($totalUsd, 2, ',', '.'),
            number_format($totalVes, 2, ',', '.'),
            number_format($remainingUsd, 2, ',', '.'),
            number_format($remainingVes, 2, ',', '.'),
            number_format($spentUsd, 2, ',', '.'),
            number_format($spentVes, 2, ',', '.'),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
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
     * @return Builder<PettyCashFund>
     */
    public function queryForUser(User $user, CarbonInterface $from, CarbonInterface $to): Builder
    {
        $query = PettyCashFund::query()
            ->whereDate('requested_at', '>=', $from->toDateString())
            ->whereDate('requested_at', '<=', $to->toDateString());

        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $query;
        }

        $branchIds = $user->restrictedBranchIdsForQueries();
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }
}
