<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'express_branch_prices')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->json('express_branch_prices')
                ->nullable()
                ->after('applies_vat')
                ->comment('Precios express por sucursal: precio final con IVA y sin IVA por porcentaje de ganancia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'express_branch_prices')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('express_branch_prices');
        });
    }
};
