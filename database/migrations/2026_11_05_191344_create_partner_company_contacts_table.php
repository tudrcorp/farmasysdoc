<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_company_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('Nombre completo del contacto');
            $table->string('position')->nullable()->comment('Cargo o rol dentro de la compañía aliada');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_responsible')->default(false)->index()->comment('Responsable del convenio por parte del aliado');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_company_contacts');
    }
};
