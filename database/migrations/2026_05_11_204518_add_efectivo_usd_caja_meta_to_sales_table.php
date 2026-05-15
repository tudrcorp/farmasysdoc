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
        Schema::table('sales', function (Blueprint $table): void {
            $table->json('efectivo_usd_caja_meta')
                ->nullable()
                ->after('bcv_ves_per_usd')
                ->comment('Asistente de vueltos en caja (billete cliente, tasa BCV, salida de caja) para cobros en efectivo USD');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('efectivo_usd_caja_meta');
        });
    }
};
