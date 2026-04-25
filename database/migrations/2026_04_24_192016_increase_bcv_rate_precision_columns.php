<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'official_usd_ves_rate')) {
            DB::statement('ALTER TABLE `purchases` MODIFY `official_usd_ves_rate` DECIMAL(18,8) NULL');
        }

        if (Schema::hasTable('purchase_histories') && Schema::hasColumn('purchase_histories', 'bcv_rate_at_payment')) {
            DB::statement('ALTER TABLE `purchase_histories` MODIFY `bcv_rate_at_payment` DECIMAL(16,8) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'official_usd_ves_rate')) {
            DB::statement('ALTER TABLE `purchases` MODIFY `official_usd_ves_rate` DECIMAL(18,2) NULL');
        }

        if (Schema::hasTable('purchase_histories') && Schema::hasColumn('purchase_histories', 'bcv_rate_at_payment')) {
            DB::statement('ALTER TABLE `purchase_histories` MODIFY `bcv_rate_at_payment` DECIMAL(16,2) NULL');
        }
    }
};
