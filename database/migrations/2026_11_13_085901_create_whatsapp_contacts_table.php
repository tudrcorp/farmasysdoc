<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->unique()->comment('Solo dígitos con código de país (58412…)');
            $table->string('profile_name', 160)->nullable()->comment('Nombre del perfil de WhatsApp');
            $table->string('full_name', 160)->nullable()->comment('Nombre y apellido confirmados por el cliente');
            $table->string('document_type', 2)->nullable();
            $table->string('document_number', 20)->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->boolean('client_data_mismatch')->default(false);
            $table->string('client_data_mismatch_note', 500)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('document_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
