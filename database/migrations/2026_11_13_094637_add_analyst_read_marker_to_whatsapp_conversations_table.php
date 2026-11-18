<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('analyst_last_read_message_id')->nullable()->after('last_agent_turn_message_id')
                ->comment('Último mensaje del cliente que vio un analista en la bandeja');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropColumn('analyst_last_read_message_id');
        });
    }
};
