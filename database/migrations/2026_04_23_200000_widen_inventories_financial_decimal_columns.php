<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DECIMAL(14,8) solo admite 6 dígitos enteros; costos mal escalados (p. ej. VES) o márgenes altos
     * disparaban SQLSTATE 22003 al insertar en inventarios.
     */
    public function up(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table): void {
            foreach ([
                'cost_price',
                'vat_cost_amount',
                'cost_plus_vat',
                'final_price_without_vat',
                'vat_final_price_amount',
                'final_price_with_vat',
            ] as $column) {
                if (Schema::hasColumn('inventories', $column)) {
                    $table->decimal($column, 22, 8)->default(0)->change();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table): void {
            foreach ([
                'cost_price',
                'vat_cost_amount',
                'cost_plus_vat',
                'final_price_without_vat',
                'vat_final_price_amount',
                'final_price_with_vat',
            ] as $column) {
                if (Schema::hasColumn('inventories', $column)) {
                    $table->decimal($column, 14, 8)->default(0)->change();
                }
            }
        });
    }
};
