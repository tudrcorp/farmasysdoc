<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->boolean('is_prescription')->default(true);
            $table->string('legibility', 20);
            $table->decimal('overall_confidence', 4, 2)->nullable();
            $table->string('doctor_name', 160)->nullable();
            $table->string('prescription_date', 40)->nullable();
            $table->json('issues')->nullable();
            $table->boolean('requires_analyst')->default(false);
            $table->boolean('has_controlled_substance')->default(false);
            $table->json('raw_response')->nullable();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_reading_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_reading_id')->constrained('prescription_readings')->cascadeOnDelete();
            $table->string('medication_name', 255);
            $table->string('concentration', 100)->nullable();
            $table->string('presentation', 150)->nullable();
            $table->string('quantity_text', 100)->nullable();
            $table->string('dosage_instructions', 500)->nullable();
            $table->decimal('confidence', 4, 2)->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_controlled_substance')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_reading_items');
        Schema::dropIfExists('prescription_readings');
    }
};
