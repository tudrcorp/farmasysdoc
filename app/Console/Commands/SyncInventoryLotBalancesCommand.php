<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use App\Services\Inventory\InventoryLotBalanceSyncService;
use Illuminate\Console\Command;

final class SyncInventoryLotBalancesCommand extends Command
{
    protected $signature = 'inventory:sync-lot-balances
                            {--purchase= : ID de compra específica}
                            {--chunk=100 : Tamaño de lote al procesar todas las compras}';

    protected $description = 'Genera o completa saldos inventory_lot_balances desde compras y lotes existentes';

    public function handle(InventoryLotBalanceSyncService $syncService): int
    {
        $purchaseId = $this->option('purchase');
        if (filled($purchaseId)) {
            $purchase = Purchase::query()->find((int) $purchaseId);
            if (! $purchase instanceof Purchase) {
                $this->error('Compra no encontrada.');

                return self::FAILURE;
            }

            $purchase->syncProductLotsFromItems();

            $this->info('Balances sincronizados para compra #'.$purchase->getKey().'.');

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $processed = 0;

        Purchase::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($purchases) use (&$processed): void {
                foreach ($purchases as $purchase) {
                    if (! $purchase instanceof Purchase) {
                        continue;
                    }

                    $purchase->syncProductLotsFromItems();
                    $processed++;
                }
            });

        $this->info("Balances sincronizados para {$processed} compra(s).");

        return self::SUCCESS;
    }
}
