<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Solo agrega pares de compras del mismo proveedor en la misma fecha a Retenciones.
 */
class DemoPurchaseBookSameDaySeeder extends Seeder
{
    public function run(): void
    {
        $demo = new DemoPurchaseBookSeeder;
        $demo->setCommand($this->command);
        $created = $demo->seedSameDayDuplicatePurchases();

        $this->command?->info("Retenciones (mismo día): {$created} registros creados.");
    }
}
