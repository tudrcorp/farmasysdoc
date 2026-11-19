<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El administrador indica, cuando la negociación lo amerita, que el precio
     * de cada medicamento al aliado es el costo más un porcentaje de ganancia.
     */
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'prices_medicines_by_cost_profit')) {
                $table->boolean('prices_medicines_by_cost_profit')
                    ->default(false)
                    ->after('has_subplans')
                    ->comment('Si el precio al aliado es costo más un porcentaje, en vez de un monto fijo por medicamento');
            }

            if (! Schema::hasColumn('plans', 'medicine_cost_profit_percent')) {
                $table->decimal('medicine_cost_profit_percent', 12, 6)
                    ->nullable()
                    ->after('prices_medicines_by_cost_profit')
                    ->comment('Porcentaje de ganancia sobre el costo de cada medicamento despachado o vendido');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table): void {
            $drops = array_values(array_filter([
                Schema::hasColumn('plans', 'medicine_cost_profit_percent') ? 'medicine_cost_profit_percent' : null,
                Schema::hasColumn('plans', 'prices_medicines_by_cost_profit') ? 'prices_medicines_by_cost_profit' : null,
            ]));

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
