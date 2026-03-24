<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_services', function (Blueprint $table): void {
            $table->json('items')
                ->nullable()
                ->after('notes')
                ->comment('Listado estructurado de medicamentos declarados en la orden (nombre por ítem)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_services', function (Blueprint $table): void {
            $table->dropColumn('items');
        });
    }
};
