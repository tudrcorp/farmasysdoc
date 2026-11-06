<?php

namespace App\Console\Commands;

use App\Services\Finance\AccountsPayableDueNoticeService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SendAccountsPayableDueNoticesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'accounts-payable:due-notices
        {--date= : Fecha de proceso (Y-m-d); por defecto hoy en America/Caracas}
        {--preview= : Solo genera el PDF en esta ruta local, sin registrar ni enviar avisos}';

    /**
     * @var string
     */
    protected $description = 'Avisa a los administradores (panel, correo y WhatsApp con PDF) las facturas de proveedores que vencen en 5 y 3 días.';

    public function handle(AccountsPayableDueNoticeService $service): int
    {
        $date = $this->option('date');
        $today = filled($date)
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $date, config('app.timezone'))->startOfDay()
            : CarbonImmutable::today(config('app.timezone'));

        if (filled($this->option('preview'))) {
            $target = (string) $this->option('preview');
            file_put_contents($target, $service->previewPdf($today));
            $this->components->info('PDF de vista previa generado en '.$target);

            return self::SUCCESS;
        }

        $result = $service->run($today);

        if (! $result['sent']) {
            $this->components->info('Sin facturas nuevas por avisar al '.$today->format('d/m/Y').'.');

            return self::SUCCESS;
        }

        $this->components->info('Aviso enviado a '.$result['recipients'].' administrador(es).');
        foreach ($result['buckets'] as $days => $count) {
            $this->components->twoColumnDetail('Por vencer en '.$days.' días', (string) $count);
        }
        $this->components->twoColumnDetail('Vencidas incluidas en el PDF', (string) $result['overdue']);
        $this->components->twoColumnDetail('PDF', Storage::disk($service->disk())->path((string) $result['pdf_path']));

        return self::SUCCESS;
    }
}
