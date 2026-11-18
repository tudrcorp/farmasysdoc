<?php

namespace App\Console\Commands;

use App\Services\Sales\PosHeldSaleReminderService;
use Illuminate\Console\Command;

class RemindPosHeldSalesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pos-held-sales:remind';

    /**
     * @var string
     */
    protected $description = 'Avisa en la campana del panel a cajeros y gerentes las ventas que llevan demasiado tiempo en pausa.';

    public function handle(PosHeldSaleReminderService $service): int
    {
        $result = $service->run();

        $this->components->info(
            'Avisos de ventas en pausa · cajeros: '.$result['cashiers_notified'].' · gerentes: '.$result['managers_notified'].'.'
        );

        return self::SUCCESS;
    }
}
