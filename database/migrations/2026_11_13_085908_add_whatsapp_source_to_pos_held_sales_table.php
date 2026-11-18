<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_held_sales', function (Blueprint $table): void {
            $table->string('source', 20)->default('pos')->after('status')
                ->comment('pos: pausada por el cajero; whatsapp: preparada por un analista para retiro en tienda');
            $table->foreignId('whatsapp_conversation_id')->nullable()->after('source')
                ->constrained('whatsapp_conversations')->nullOnDelete();

            $table->index(['source', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_held_sales', function (Blueprint $table): void {
            $table->dropIndex(['source', 'branch_id', 'status']);
            $table->dropConstrainedForeignId('whatsapp_conversation_id');
            $table->dropColumn('source');
        });
    }
};
