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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la línea de pedido');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete()->comment('Pedido al que pertenece la línea');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->comment('Producto solicitado');
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete()->comment('Inventario de sucursal desde el que se reservará o despachará');
            $table->decimal('quantity', 12, 3)->comment('Cantidad pedida en unidades de medida del producto');
            $table->decimal('unit_price', 12, 2)->comment('Precio unitario acordado o cotizado');
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('Descuento en valor monetario sobre la línea');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Porcentaje de impuesto aplicado a la línea');
            $table->decimal('line_subtotal', 12, 2)->comment('Subtotal de línea antes de impuesto');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('Monto de impuesto de la línea');
            $table->decimal('line_total', 12, 2)->comment('Total de la línea (subtotal + impuesto)');
            $table->string('product_name_snapshot')->nullable()->comment('Nombre del producto congelado al armar el pedido');
            $table->string('sku_snapshot')->nullable()->comment('SKU congelado al armar el pedido');
            $table->timestamps();

            $table->index(['order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
