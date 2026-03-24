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
        Schema::create('order_services', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la orden de servicio');
            $table->string('service_order_number')->unique()->comment('Consecutivo visible de la orden de servicio');
            $table->unsignedBigInteger('partner_company_id')->comment('Compañía aliada que solicita o patrocina la orden');
            $table->unsignedInteger('client_id')->nullable()->comment('Cliente final asociado, cuando aplique');
            $table->unsignedInteger('branch_id')->nullable()->comment('Sucursal que ejecuta o coordina el servicio');
            $table->string('status')->index()->comment('Estado operativo: borrador, aprobada, en proceso, finalizada, cancelada');
            $table->string('priority')->default('media')->index()->comment('Prioridad: baja, media, alta, urgente');
            $table->string('service_type')->nullable()->comment('Tipo de servicio (consulta, procedimiento, visita, etc.)');

            $table->string('authorization_reference')->nullable()->comment('Referencia de autorización de la compañía aliada');
            $table->string('external_reference')->nullable()->comment('Referencia externa de la compañía aliada o del cliente');

            $table->string('patient_name')->nullable()->comment('Nombre del paciente o beneficiario');
            $table->string('patient_document')->nullable()->comment('Documento del paciente o beneficiario');
            $table->string('patient_phone')->nullable()->comment('Teléfono de contacto del paciente');

            $table->timestamp('ordered_at')->nullable()->comment('Fecha de emisión de la orden');
            $table->timestamp('scheduled_at')->nullable()->comment('Fecha y hora programada');
            $table->timestamp('started_at')->nullable()->comment('Inicio de la atención o ejecución');
            $table->timestamp('completed_at')->nullable()->comment('Cierre de la orden de servicio');

            $table->decimal('subtotal', 12, 2)->default(0)->comment('Suma de ítems antes de impuestos y descuentos');
            $table->decimal('tax_total', 12, 2)->default(0)->comment('Total de impuestos de la orden');
            $table->decimal('discount_total', 12, 2)->default(0)->comment('Total de descuentos de la orden');
            $table->decimal('total', 12, 2)->default(0)->comment('Total final (subtotal + impuestos - descuentos)');

            $table->text('diagnosis')->nullable()->comment('Diagnóstico o motivo clínico/comercial de la orden');
            $table->text('notes')->nullable()->comment('Observaciones internas');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que creó la orden');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que actualizó la orden por última vez');
            $table->timestamps();

            $table->index(['partner_company_id', 'created_at']);
            $table->index(['branch_id', 'status']);
            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_services');
    }
};
