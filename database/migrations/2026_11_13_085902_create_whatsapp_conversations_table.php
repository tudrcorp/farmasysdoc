<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_contact_id')->constrained('whatsapp_contacts')->cascadeOnDelete();
            $table->string('status', 30)->default('bot');
            $table->string('handled_by', 20)->default('agent');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('fulfillment_type', 20)->nullable();
            $table->string('delivery_address', 500)->nullable();
            $table->string('delivery_reference', 255)->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
            $table->string('delivery_recipient_name', 160)->nullable();
            $table->string('delivery_recipient_phone', 30)->nullable();
            $table->string('payment_method_note', 255)->nullable();
            $table->json('state')->nullable()->comment('Etapa, pregunta pendiente y notas del agente');
            $table->text('summary')->nullable();
            $table->string('escalation_reason', 500)->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->boolean('escalated_off_hours')->default(false);
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->unsignedBigInteger('last_agent_turn_message_id')->nullable()->comment('Último mensaje entrante que ya respondió el agente');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedBigInteger('pos_held_sale_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'escalated_at']);
            $table->index(['whatsapp_contact_id', 'status']);
            $table->index('last_inbound_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
