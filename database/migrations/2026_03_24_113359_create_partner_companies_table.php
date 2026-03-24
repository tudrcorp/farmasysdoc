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
        Schema::create('partner_companies', function (Blueprint $table) {
            $table->id()->comment('Identificador único de la compañía aliada');
            $table->string('code')->nullable()->unique()->comment('Código interno de la compañía aliada');
            $table->string('legal_name')->comment('Razón social de la entidad aliada');
            $table->string('trade_name')->nullable()->comment('Nombre comercial');
            $table->string('tax_id')->nullable()->index()->comment('NIT u otra identificación fiscal');
            $table->string('email')->nullable()->comment('Correo principal');
            $table->string('phone')->nullable()->comment('Teléfono principal');
            $table->string('mobile_phone')->nullable()->comment('Teléfono móvil');
            $table->string('website')->nullable()->comment('Sitio web');
            $table->string('address')->nullable()->comment('Dirección principal');
            $table->string('city')->nullable()->comment('Ciudad');
            $table->string('state')->nullable()->comment('Departamento o estado');
            $table->string('country')->default('Colombia')->comment('País');
            $table->string('contact_name')->nullable()->comment('Nombre del contacto principal');
            $table->string('contact_email')->nullable()->comment('Correo del contacto principal');
            $table->string('contact_phone')->nullable()->comment('Teléfono del contacto principal');
            $table->string('agreement_reference')->nullable()->comment('Código o referencia del convenio');
            $table->text('agreement_terms')->nullable()->comment('Condiciones del convenio con la compañía aliada');
            $table->text('notes')->nullable()->comment('Observaciones internas');
            $table->boolean('is_active')->default(true)->index()->comment('Define si la compañía está habilitada');
            $table->string('created_by')->nullable()->comment('Usuario o sistema que creó el registro');
            $table->string('updated_by')->nullable()->comment('Usuario o sistema que actualizó por última vez');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_companies');
    }
};
