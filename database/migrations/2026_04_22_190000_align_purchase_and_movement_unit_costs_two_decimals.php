<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('purchase_items') && Schema::hasColumn('purchase_items', 'unit_cost')) {
            DB::statement('ALTER TABLE `purchase_items` MODIFY `unit_cost` DECIMAL(14,2) NOT NULL DEFAULT 0');
        }

        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'official_usd_ves_rate')) {
            DB::statement('ALTER TABLE `purchases` MODIFY `official_usd_ves_rate` DECIMAL(18,2) NULL');
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'unit_cost')) {
            DB::statement('ALTER TABLE `inventory_movements` MODIFY `unit_cost` DECIMAL(12,2) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('purchase_items') && Schema::hasColumn('purchase_items', 'unit_cost')) {
            DB::statement('ALTER TABLE `purchase_items` MODIFY `unit_cost` DECIMAL(12,4) NOT NULL DEFAULT 0');
        }

        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'official_usd_ves_rate')) {
            DB::statement('ALTER TABLE `purchases` MODIFY `official_usd_ves_rate` DECIMAL(18,8) NULL');
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'unit_cost')) {
            DB::statement('ALTER TABLE `inventory_movements` MODIFY `unit_cost` DECIMAL(12,4) NULL');
        }
    }
};
