<?php

namespace App\Console\Commands;

use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\AccountsPayableCurrentBalanceRecalculator;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\AccountsPayableStatus;
use Illuminate\Console\Command;

class RecalculateAccountsPayableCurrentBalancesCommand extends Command
{
    protected $signature = 'accounts-payable:recalculate-current-balances';

    protected $description = 'Recalcula el saldo en bolívares (tasa BCV del día) de todas las cuentas por pagar';

    public function handle(
        VenezuelaOfficialUsdVesRateClient $rateClient,
        AccountsPayableCurrentBalanceRecalculator $recalculator,
    ): int {
        $rateToday = $rateClient->rateForDate(now());

        if ($rateToday === null || $rateToday <= 0) {
            AuditLogger::record(
                event: 'accounts_payable_daily_recalc_rate_unavailable',
                description: 'Cuentas por pagar: tarea diaria omitida por no disponer de tasa BCV oficial para la fecha en curso.',
                properties: [
                    'target_date' => now()->toDateString(),
                ],
            );

            $this->warn('No hay tasa BCV disponible para hoy; no se actualizaron saldos.');

            return self::SUCCESS;
        }

        $processed = 0;
        $changed = 0;

        AccountsPayable::query()
            ->where('status', AccountsPayableStatus::POR_PAGAR)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($recalculator, &$processed, &$changed): void {
                foreach ($chunk as $ap) {
                    /** @var AccountsPayable $ap */
                    $result = $recalculator->recalculate($ap, audit: false);

                    if (! $result['ok']) {
                        continue;
                    }

                    if (abs((float) $result['previous_balance_ves'] - (float) $result['new_balance_ves']) >= 0.005) {
                        $changed++;
                    }

                    $processed++;
                }
            });

        AuditLogger::record(
            event: 'accounts_payable_daily_recalc_completed',
            description: 'Cuentas por pagar: finalizó la tarea programada de recálculo de saldos en Bs (tasa BCV del día).',
            properties: [
                'records_processed' => $processed,
                'records_with_balance_change' => $changed,
                'bcv_rate_applied' => $rateToday,
                'as_of' => now()->toIso8601String(),
            ],
        );

        $this->info("Registros procesados: {$processed} (importe en Bs distinto al anterior: {$changed}).");

        return self::SUCCESS;
    }
}
