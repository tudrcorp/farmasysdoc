<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('shop_customers') && ! Schema::hasTable('pwa_customers')) {
            Schema::rename('shop_customers', 'pwa_customers');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pwa_customers') && ! Schema::hasTable('shop_customers')) {
            Schema::rename('pwa_customers', 'shop_customers');
        }
    }
};
