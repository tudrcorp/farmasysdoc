<?php

namespace App\Console\Commands;

use App\Services\Partners\PartnerAgreementLifecycleRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProcessPartnerAgreementsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'partner-agreements:process {--date= : Fecha de proceso (Y-m-d); por defecto hoy en America/Caracas}';

    /**
     * @var string
     */
    protected $description = 'Activa convenios aprobados, vence o renueva automáticamente los que terminaron y envía avisos de vencimiento.';

    public function handle(PartnerAgreementLifecycleRunner $runner): int
    {
        $date = $this->option('date');

        $today = filled($date)
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $date, config('app.timezone'))->startOfDay()
            : CarbonImmutable::today(config('app.timezone'));

        $summary = $runner->run($today);

        $this->components->info('Convenios procesados al '.$today->format('d/m/Y'));
        $this->components->twoColumnDetail('Entraron en vigencia', (string) $summary['activated']);
        $this->components->twoColumnDetail('Renovados automáticamente', (string) $summary['renewed']);
        $this->components->twoColumnDetail('Vencidos', (string) $summary['expired']);
        $this->components->twoColumnDetail('Avisos de vencimiento', (string) $summary['notices']);
        $this->components->twoColumnDetail('Errores', (string) $summary['errors']);

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
