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
        Schema::table('inventories', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventories', 'cost_price')) {
                $table->decimal('cost_price', 14, 8)
                    ->default(0)
                    ->after('product_id')
                    ->comment('Costo unitario de referencia en inventario (snapshot al recibir compra)');
            }

            if (! Schema::hasColumn('inventories', 'vat_cost_amount')) {
                $table->decimal('vat_cost_amount', 14, 8)
                    ->default(0)
                    ->after('cost_price')
                    ->comment('Valor IVA sobre costo: costo × tasa IVA del sistema');
            }

            if (! Schema::hasColumn('inventories', 'cost_plus_vat')) {
                $table->decimal('cost_plus_vat', 14, 8)
                    ->default(0)
                    ->after('vat_cost_amount')
                    ->comment('Costo + IVA sobre costo');
            }

            if (! Schema::hasColumn('inventories', 'final_price_without_vat')) {
                $table->decimal('final_price_without_vat', 14, 8)
                    ->default(0)
                    ->after('cost_plus_vat')
                    ->comment('Precio final sin IVA: costo + (costo × % ganancia de categoría)');
            }

            if (! Schema::hasColumn('inventories', 'vat_final_price_amount')) {
                $table->decimal('vat_final_price_amount', 14, 8)
                    ->default(0)
                    ->after('final_price_without_vat')
                    ->comment('Valor IVA del precio final sin IVA');
            }

            if (! Schema::hasColumn('inventories', 'final_price_with_vat')) {
                $table->decimal('final_price_with_vat', 14, 8)
                    ->default(0)
                    ->after('vat_final_price_amount')
                    ->comment('Precio final con IVA');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $toDrop = array_values(array_filter([
                Schema::hasColumn('inventories', 'cost_price') ? 'cost_price' : null,
                Schema::hasColumn('inventories', 'vat_cost_amount') ? 'vat_cost_amount' : null,
                Schema::hasColumn('inventories', 'cost_plus_vat') ? 'cost_plus_vat' : null,
                Schema::hasColumn('inventories', 'final_price_without_vat') ? 'final_price_without_vat' : null,
                Schema::hasColumn('inventories', 'vat_final_price_amount') ? 'vat_final_price_amount' : null,
                Schema::hasColumn('inventories', 'final_price_with_vat') ? 'final_price_with_vat' : null,
            ]));

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
