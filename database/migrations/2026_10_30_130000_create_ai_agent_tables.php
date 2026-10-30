<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->timestamps();
        });

        Schema::create('ai_agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_conversation_id')->constrained('ai_agent_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->boolean('failed')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_agent_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_message_id')->nullable()->constrained('ai_agent_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->json('columns');
            $table->longText('rows');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_reports');
        Schema::dropIfExists('ai_agent_messages');
        Schema::dropIfExists('ai_agent_conversations');
    }
};
