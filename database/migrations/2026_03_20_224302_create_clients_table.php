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
        Schema::create('clients', function (Blueprint $table) {
            $table->id()->comment('Identificador único del cliente');
            $table->string('name')->comment('Nombre completo o razón social');
            $table->string('document_type')->comment('Tipo de documento (CC, NIT, CE, PAS, etc.)');
            $table->string('document_number')->comment('Número del documento de identificación');
            $table->string('email')->unique()->comment('Correo electrónico de contacto (único)');
            $table->string('phone')->comment('Teléfono principal');
            $table->string('address')->comment('Dirección de residencia o fiscal');
            $table->string('city')->comment('Ciudad');
            $table->string('state')->comment('Departamento o estado');
            $table->string('country')->comment('País');
            $table->string('status')->comment('Estado del registro (activo, inactivo, bloqueado, etc.)');
            $table->string('created_by')->comment('Usuario o sistema que creó el registro');
            $table->string('updated_by')->comment('Usuario o sistema que actualizó el registro por última vez');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
