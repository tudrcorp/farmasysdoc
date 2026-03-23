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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id()->comment('Identificador único del proveedor');
            $table->string('code')->nullable()->unique()->comment('Código interno del proveedor');
            $table->string('legal_name')->comment('Razón social');
            $table->string('trade_name')->nullable()->comment('Nombre comercial');
            $table->string('tax_id')->comment('NIT u otra identificación fiscal');
            $table->string('email')->nullable()->comment('Correo electrónico principal');
            $table->string('phone')->nullable()->comment('Teléfono fijo');
            $table->string('mobile_phone')->nullable()->comment('Teléfono móvil');
            $table->string('website')->nullable()->comment('Sitio web del proveedor');
            $table->string('address')->nullable()->comment('Dirección fiscal o de correspondencia');
            $table->string('city')->nullable()->comment('Ciudad');
            $table->string('state')->nullable()->comment('Departamento o estado');
            $table->string('country')->default('Colombia')->comment('País');
            $table->string('contact_name')->nullable()->comment('Persona de contacto comercial');
            $table->string('contact_email')->nullable()->comment('Correo del contacto');
            $table->string('contact_phone')->nullable()->comment('Teléfono del contacto');
            $table->text('payment_terms')->nullable()->comment('Condiciones y plazos de pago acordados');
            $table->text('notes')->nullable()->comment('Observaciones internas');
            $table->boolean('is_active')->default(true)->comment('Si el proveedor está activo para compras');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que creó el registro');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que actualizó el registro por última vez');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
