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
        Schema::create('farma_express_cost_structures', function (Blueprint $table) {
            $table->id()->comment('Identificador del porcentaje de ganancia para sucursal express');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Sucursal express asociada');
            $table->decimal('profit_percentage', 5, 2)->comment('Porcentaje de ganancia aplicado a la sucursal express');
            $table->timestamps();

            $table->unique('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farma_express_cost_structures');
    }
};
