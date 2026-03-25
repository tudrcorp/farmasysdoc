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
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)
                ->nullable()
                ->after('unit_price')
                ->comment('Costo unitario del producto al momento de la venta');
            $table->decimal('line_cost_total', 12, 2)
                ->nullable()
                ->after('line_total')
                ->comment('Costo total de la línea (cantidad × costo unitario)');
            $table->decimal('gross_profit', 12, 2)
                ->nullable()
                ->after('line_cost_total')
                ->comment('Ganancia bruta de la línea (line_total - line_cost_total)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_cost',
                'line_cost_total',
                'gross_profit',
            ]);
        });
    }
};
