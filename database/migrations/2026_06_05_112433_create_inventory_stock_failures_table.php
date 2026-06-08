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
        Schema::create('fallas_existencia', function (Blueprint $table): void {
            $table->id()->comment('Registro de intento de venta con existencia cero en caja');
            $table->foreignId('branch_id')->comment('Sucursal donde ocurrió la falla')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->comment('Producto consultado')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Usuario (cajero) que intentó agregar el producto')->constrained('users')->cascadeOnDelete();
            $table->string('product_code', 128)->comment('Código de barras, SKU u otro identificador del producto al momento del evento');
            $table->string('product_name')->comment('Nombre del producto al momento del evento');
            $table->decimal('quantity', 15, 3)->default(0)->comment('Existencia registrada (normalmente 0)');
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fallas_existencia');
    }
};
