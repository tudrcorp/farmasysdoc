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
        Schema::create('order_service_items', function (Blueprint $table) {
            $table->id()->comment('Identificador único del ítem de orden de servicio');
            $table->unsignedBigInteger('order_service_id')->comment('Orden de servicio padre');
            $table->unsignedInteger('product_id')->nullable()->comment('Producto asociado al ítem (si aplica)');
            $table->unsignedInteger('inventory_id')->nullable()->comment('Inventario origen para consumos del servicio');
            $table->string('item_type')->default('servicio')->comment('Tipo de ítem: servicio, insumo, medicamento, dispositivo, otro');
            $table->string('service_code')->nullable()->comment('Código del procedimiento o servicio');
            $table->string('description')->comment('Descripción del ítem o actividad');
            $table->decimal('quantity', 12, 3)->default(1)->comment('Cantidad del ítem');
            $table->decimal('unit_price', 12, 2)->default(0)->comment('Valor unitario');
            $table->decimal('discount_amount', 12, 2)->default(0)->comment('Descuento aplicado al ítem');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Porcentaje de impuesto');
            $table->decimal('line_subtotal', 12, 2)->default(0)->comment('Subtotal de línea antes de impuesto');
            $table->decimal('tax_amount', 12, 2)->default(0)->comment('Impuesto calculado de la línea');
            $table->decimal('line_total', 12, 2)->default(0)->comment('Total de la línea');
            $table->string('product_name_snapshot')->nullable()->comment('Nombre del producto al momento de registrar el ítem');
            $table->string('sku_snapshot')->nullable()->comment('SKU del producto al momento de registrar el ítem');
            $table->text('notes')->nullable()->comment('Notas u observaciones del ítem');
            $table->timestamps();

            $table->index(['order_service_id', 'item_type']);
            $table->index(['order_service_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_service_items');
    }
};
