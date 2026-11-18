<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_agent_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('analyst_idle_minutes')->default(10)->after('message_grouping_seconds')
                ->comment('Si el analista no responde en estos minutos, el asistente retoma la conversación (0 = nunca)');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_agent_settings', function (Blueprint $table): void {
            $table->dropColumn('analyst_idle_minutes');
        });
    }
};
