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
        Schema::create('orders', function (Blueprint $table) {
            $table->id()->comment('Identificador único del pedido');
            $table->string('order_number')->unique()->comment('Número de pedido visible para seguimiento');
            $table->unsignedInteger('client_id')->comment('Cliente que realizó el pedido');
            $table->unsignedInteger('branch_id')->nullable()->comment('Sucursal que prepara, factura o despacha el pedido');
            $table->string('status')->index()->comment('Estado del flujo: pendiente, confirmado, en preparación, listo, enviado, en ruta, entregado, cancelado');

            $table->string('convenio_type')->index()->comment('Tipo de convenio: particular, seguro privado, EPS, medicina prepagada, corporativo, otro');
            $table->string('convenio_partner_name')->nullable()->comment('Nombre del aliado: aseguradora, EPS o empresa del convenio');
            $table->string('convenio_reference')->nullable()->comment('Número de autorización, póliza o código del convenio');
            $table->text('convenio_notes')->nullable()->comment('Detalle adicional del convenio o cobertura');

            $table->string('delivery_recipient_name')->nullable()->comment('Nombre de quien recibe el envío');
            $table->string('delivery_phone')->nullable()->comment('Teléfono de contacto para la entrega');
            $table->string('delivery_address')->nullable()->comment('Dirección completa de entrega');
            $table->string('delivery_city')->nullable()->comment('Ciudad de entrega');
            $table->string('delivery_state')->nullable()->comment('Departamento o estado de entrega');
            $table->text('delivery_notes')->nullable()->comment('Indicaciones para el equipo de delivery (torre, horario, etc.)');

            $table->timestamp('scheduled_delivery_at')->nullable()->comment('Fecha y hora programada de entrega');
            $table->timestamp('dispatched_at')->nullable()->comment('Momento en que salió el pedido del punto de despacho');
            $table->timestamp('delivered_at')->nullable()->comment('Momento en que se confirmó la entrega al cliente');
            $table->string('delivery_assignee')->nullable()->comment('Responsable o equipo de delivery asignado');

            $table->decimal('subtotal', 12, 2)->default(0)->comment('Suma de líneas antes de impuestos y descuentos globales');
            $table->decimal('tax_total', 12, 2)->default(0)->comment('Total de impuestos del pedido');
            $table->decimal('discount_total', 12, 2)->default(0)->comment('Descuentos globales al documento');
            $table->decimal('total', 12, 2)->default(0)->comment('Total del pedido (subtotal + impuestos - descuentos)');

            $table->text('notes')->nullable()->comment('Observaciones generales del pedido');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que creó el pedido');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que actualizó el pedido por última vez');
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
