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

        $result = $recalculator->recalculateMany(
            AccountsPayable::query()->where('status', AccountsPayableStatus::POR_PAGAR),
            rateOverride: $rateToday,
            audit: false,
            withLines: false,
        );

        if (! $result['ok']) {
            $this->warn((string) $result['error']);

            return self::SUCCESS;
        }

        AuditLogger::record(
            event: 'accounts_payable_daily_recalc_completed',
            description: 'Cuentas por pagar: finalizó la tarea programada de recálculo del total a pagar (tasa BCV del día, 2 decimales).',
            properties: [
                'records_processed' => $result['processed'],
                'records_with_balance_change' => $result['changed'],
                'records_failed' => $result['failed'],
                'bcv_rate_applied' => $result['rate'],
                'as_of' => now()->toIso8601String(),
            ],
        );

        $this->info('Registros procesados: '.$result['processed'].' (importe en Bs distinto al anterior: '.$result['changed'].', omitidos: '.$result['failed'].').');

        return self::SUCCESS;
    }
}
