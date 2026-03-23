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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id()->comment('Identificador único del movimiento');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->comment('Producto afectado');
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete()->comment('Registro de inventario relacionado (sucursal + producto)');
            $table->string('movement_type')->index()->comment('Tipo: compra, venta, ajuste, devolución, pérdida, transferencia, inventario físico, etc.');
            $table->decimal('quantity', 12, 3)->comment('Cantidad con signo: positivo entrada, negativo salida');
            $table->decimal('unit_cost', 12, 4)->nullable()->comment('Costo unitario al momento del movimiento (valoración)');
            $table->string('batch_number')->nullable()->comment('Número de lote (trazabilidad)');
            $table->date('expiry_date')->nullable()->comment('Fecha de vencimiento del lote');
            $table->nullableMorphs('reference');
            $table->text('notes')->nullable()->comment('Observaciones del movimiento');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que registró el movimiento');
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
