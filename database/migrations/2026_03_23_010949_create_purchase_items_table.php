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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la línea de compra');
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete()->comment('Orden de compra a la que pertenece la línea');
            $table->foreignId('product_id')->constrained()->restrictOnDelete()->comment('Producto comprado');
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete()->comment('Inventario de sucursal destino (opcional, al vincular recepción)');
            $table->decimal('quantity_ordered', 12, 3)->comment('Cantidad pedida al proveedor');
            $table->decimal('quantity_received', 12, 3)->default(0)->comment('Cantidad ya recibida (recepciones parciales)');
            $table->decimal('unit_cost', 12, 4)->comment('Costo unitario negociado en la compra');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Porcentaje de impuesto aplicado a la línea');
            $table->decimal('line_subtotal', 12, 2)->comment('Subtotal de línea antes de impuesto');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('Monto de impuesto de la línea');
            $table->decimal('line_total', 12, 2)->comment('Total de la línea (subtotal + impuesto)');
            $table->string('product_name_snapshot')->nullable()->comment('Nombre del producto al momento de la compra');
            $table->string('sku_snapshot')->nullable()->comment('SKU al momento de la compra');
            $table->text('notes')->nullable()->comment('Observaciones de la línea');
            $table->timestamps();

            $table->index(['purchase_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
