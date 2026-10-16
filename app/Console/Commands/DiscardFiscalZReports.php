<?php

namespace App\Console\Commands;

use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Services\Audit\AuditLogger;
use App\Services\Fiscal\SalesBookPeriodLock;
use App\Support\Fiscal\SalesBookPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('fiscal:discard-z-reports {serial : Registro de la máquina, p. ej. Z7C7039908} {z* : Números de Z a descartar} {--reason= : Motivo (obligatorio, queda en la auditoría)} {--force : No pedir confirmación}')]
#[Description('Descarta reportes Z capturados con datos inválidos para volver a leerlos de la memoria fiscal (nunca si su quincena tiene Libro de Ventas cerrado)')]
final class DiscardFiscalZReports extends Command
{
    public function handle(SalesBookPeriodLock $lock): int
    {
        $reason = trim((string) $this->option('reason'));

        if (mb_strlen($reason) < 10) {
            $this->error('Indique --reason con el motivo (mínimo 10 caracteres).');

            return self::FAILURE;
        }

        $serial = (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $this->argument('serial')));
        $numbers = array_map('intval', (array) $this->argument('z'));

        $reports = FiscalZReport::query()
            ->where('printer_serial', $serial)
            ->whereIn('z_number', $numbers)
            ->orderBy('z_number')
            ->get();

        if ($reports->isEmpty()) {
            $this->error('No hay reportes Z '.implode(', ', $numbers).' guardados para '.$serial.'.');

            return self::FAILURE;
        }

        foreach ($reports as $report) {
            if ($lock->isClosed((int) $report->branch_id, SalesBookPeriod::containing($report->report_date))) {
                $this->error('El Z '.$report->z_number.' pertenece a una quincena con Libro de Ventas cerrado; reábralo primero.');

                return self::FAILURE;
            }
        }

        $this->table(
            ['Z', 'Fecha', 'Última factura', 'Total ventas', 'Origen'],
            $reports->map(fn (FiscalZReport $report): array => [
                $report->z_number,
                $report->report_date?->format('d/m/Y'),
                $report->last_invoice_number,
                number_format($report->salesTotalVes(), 2, ',', '.'),
                $report->source->label(),
            ])->all(),
        );

        if (! $this->option('force') && ! $this->confirm('¿Descartar estos '.$reports->count().' reporte(s) Z?')) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($reports, $reason): void {
            foreach ($reports as $report) {
                AuditLogger::record(
                    'fiscal_z_report_discarded',
                    'Fiscal · Z Nº '.$report->z_number.' de '.$report->printer_serial.' descartado: '.$reason,
                    FiscalZReport::class,
                    $report->id,
                    'Z '.$report->z_number,
                    ['module' => 'fiscal', 'reason' => $reason, 'discarded' => $report->getAttributes()],
                );
            }

            FiscalZReport::query()->whereKey($reports->modelKeys())->delete();
        });

        $this->info('Descartados. Vuelva a leerlos con «Leer reportes Z» (agente '.FiscalPrinter::Z_READING_MIN_AGENT_VERSION.' o superior).');

        return self::SUCCESS;
    }
}
