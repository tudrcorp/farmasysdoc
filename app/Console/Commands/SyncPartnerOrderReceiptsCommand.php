<?php

namespace App\Console\Commands;

use App\Services\Orders\PartnerOrderReceiptSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:sync-partner-receipts')]
#[Description('Consulta en Tu Dr. Group los pedidos registrados en inventario y guarda la nota de recibido')]
class SyncPartnerOrderReceiptsCommand extends Command
{
    public function handle(PartnerOrderReceiptSync $sync): int
    {
        if (! $sync->hasToken()) {
            $this->warn('No hay token de Tu Dr. Group para consultar las recepciones.');

            return self::SUCCESS;
        }

        $stored = $sync->syncAll();
        $this->info('Recepciones consultadas: '.$stored.'.');

        return self::SUCCESS;
    }
}
