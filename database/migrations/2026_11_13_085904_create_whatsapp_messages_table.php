<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('author', 20)->comment('customer, agent, analyst, system');
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->string('provider_message_id', 191)->nullable()->unique();
            $table->string('media_url', 1000)->nullable()->comment('URL temporal del proveedor');
            $table->string('media_path', 500)->nullable()->comment('Copia en disco privado');
            $table->string('media_mime', 100)->nullable();
            $table->text('transcription')->nullable()->comment('Texto de una nota de voz');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('delivery_failed')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
