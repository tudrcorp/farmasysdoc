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
        Schema::create('sales', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la venta');
            $table->string('sale_number')->unique()->comment('Número de venta o factura visible para el cliente');
            $table->unsignedInteger('branch_id')->comment('Sucursal donde se realizó la venta');
            $table->unsignedInteger('client_id')->nullable()->comment('Cliente asociado (null si es venta sin datos de cliente)');
            $table->string('status')->index()->comment('Estado: borrador, completada, anulada, devuelta (valores del enum de aplicación)');
            $table->decimal('subtotal', 12, 2)->default(0)->comment('Suma de líneas antes de impuestos y descuentos globales');
            $table->decimal('tax_total', 12, 2)->default(0)->comment('Total de impuestos de la venta');
            $table->decimal('discount_total', 12, 2)->default(0)->comment('Descuentos globales aplicados al documento');
            $table->decimal('total', 12, 2)->default(0)->comment('Total a pagar (subtotal + impuestos - descuentos)');
            $table->string('payment_method')->nullable()->comment('Medio de pago: efectivo, tarjeta, transferencia, mixto, etc.');
            $table->string('payment_status')->nullable()->comment('Estado del cobro: pagado, parcial, pendiente');
            $table->text('notes')->nullable()->comment('Observaciones de la venta');
            $table->timestamp('sold_at')->nullable()->comment('Fecha y hora en que se concretó la venta');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que registró la venta');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que modificó el registro por última vez');
            $table->timestamps();

            $table->index(['branch_id', 'sold_at']);
            $table->index(['client_id', 'sold_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
