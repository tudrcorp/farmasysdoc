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
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'partner_delivery_rating')) {
                $table->unsignedTinyInteger('partner_delivery_rating')
                    ->nullable()
                    ->after('delivered_at')
                    ->comment('Calificación 1–5 del servicio de entrega (panel aliado al marcar finalizado)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'partner_delivery_rating')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('partner_delivery_rating');
        });
    }
};
