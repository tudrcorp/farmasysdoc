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
        Schema::create('branches', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la sucursal');
            $table->string('code')->unique()->comment('Código interno de la sucursal');
            $table->string('name')->comment('Nombre comercial o de mostrador');
            $table->string('legal_name')->nullable()->comment('Razón social si difiere del nombre comercial');
            $table->string('tax_id')->nullable()->comment('NIT u otra identificación fiscal de la sucursal');
            $table->string('email')->nullable()->comment('Correo electrónico de la sucursal');
            $table->string('phone')->nullable()->comment('Teléfono fijo');
            $table->string('mobile_phone')->nullable()->comment('Teléfono móvil o WhatsApp');
            $table->string('address')->nullable()->comment('Dirección física');
            $table->string('city')->nullable()->comment('Ciudad');
            $table->string('state')->nullable()->comment('Departamento o estado');
            $table->string('country')->default('Colombia')->comment('País');
            $table->boolean('is_headquarters')->default(false)->comment('Indica si es la sede principal de la empresa');
            $table->boolean('is_active')->default(true)->comment('Si la sucursal opera y aparece en listados');
            $table->text('notes')->nullable()->comment('Observaciones internas sobre la sucursal');
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
        Schema::dropIfExists('branches');
    }
};
