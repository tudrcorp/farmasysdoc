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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro de inventario');
            $table->unsignedInteger('branch_id')->comment('Sucursal donde está el stock');
            $table->unsignedInteger('product_id')->comment('Producto al que pertenecen las existencias');
            $table->decimal('quantity', 12, 3)->default(0)->comment('Existencias físicas actuales');
            $table->decimal('reserved_quantity', 12, 3)->default(0)->comment('Cantidad apartada (pedidos, apartados, reservas)');
            $table->decimal('reorder_point', 12, 3)->nullable()->comment('Umbral para alertar reorden al igualar o bajar de este nivel');
            $table->decimal('minimum_stock', 12, 3)->nullable()->comment('Stock mínimo deseado de política');
            $table->decimal('maximum_stock', 12, 3)->nullable()->comment('Tope sugerido de almacenamiento');
            $table->string('storage_location')->nullable()->comment('Ubicación física: pasillo, góndola, nevera, etc.');
            $table->boolean('allow_negative_stock')->default(false)->comment('Permite saldo negativo en el sistema (casos excepcionales)');
            $table->timestamp('last_movement_at')->nullable()->comment('Fecha del último movimiento de inventario');
            $table->timestamp('last_stock_take_at')->nullable()->comment('Fecha del último conteo físico o auditoría');
            $table->text('notes')->nullable()->comment('Notas sobre el stock o la ubicación');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que creó el registro');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que actualizó el registro por última vez');
            $table->timestamps();

            $table->unique(['product_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
