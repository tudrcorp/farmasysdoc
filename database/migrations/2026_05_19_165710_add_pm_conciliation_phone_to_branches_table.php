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
        if (Schema::hasColumn('branches', 'pm_conciliation_phone')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->string('pm_conciliation_phone')
                ->nullable()
                ->after('mobile_phone')
                ->comment('Teléfono destino (comercio) para conciliación Pago Móvil BDV por sucursal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'pm_conciliation_phone')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('pm_conciliation_phone');
        });
    }
};
