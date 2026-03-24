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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la compra / orden de compra');
            $table->string('purchase_number')->unique()->comment('Número interno de la orden de compra');
            $table->unsignedInteger('supplier_id')->comment('Proveedor al que se compra');
            $table->unsignedInteger('branch_id')->comment('Sucursal donde se recibe o ingresa la mercancía');
            $table->string('status')->index()->comment('Estado: borrador, pedido, recepción parcial, recibido, anulado');

            $table->timestamp('ordered_at')->nullable()->comment('Fecha en que se envió el pedido al proveedor');
            $table->timestamp('expected_delivery_at')->nullable()->comment('Fecha estimada de entrega acordada');
            $table->timestamp('received_at')->nullable()->comment('Fecha en que se completó la recepción total');

            $table->decimal('subtotal', 12, 2)->default(0)->comment('Suma de líneas antes de impuestos y descuentos globales');
            $table->decimal('tax_total', 12, 2)->default(0)->comment('Total de impuestos de la compra');
            $table->decimal('discount_total', 12, 2)->default(0)->comment('Descuentos globales del documento');
            $table->decimal('total', 12, 2)->default(0)->comment('Total de la compra (subtotal + impuestos - descuentos)');

            $table->string('supplier_invoice_number')->nullable()->comment('Número de factura emitida por el proveedor');
            $table->string('payment_status')->nullable()->comment('Estado del pago al proveedor: pendiente, parcial, pagado');
            $table->text('notes')->nullable()->comment('Observaciones de la orden de compra');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que registró la compra');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que actualizó el registro por última vez');
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
