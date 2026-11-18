<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_agent_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('test_mode')->default(true);
            $table->json('test_phone_numbers')->nullable();
            $table->string('agent_name', 80)->default('Isabella');
            $table->string('ultramsg_instance_url', 255)->nullable();
            $table->text('ultramsg_token')->nullable();
            $table->string('webhook_secret', 64);
            $table->string('n8n_webhook_url', 500)->nullable();
            $table->text('agent_api_token')->nullable();
            $table->json('analyst_schedule')->nullable();
            $table->unsignedSmallInteger('message_grouping_seconds')->default(10);
            $table->decimal('prescription_confidence_threshold', 4, 2)->default(0.70);
            $table->longText('system_prompt')->nullable();
            $table->text('suggested_products_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_agent_settings');
    }
};
