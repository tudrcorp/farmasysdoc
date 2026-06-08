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
        Schema::table('cajas_fisicas', function (Blueprint $table): void {
            $table->string('close_usd_cash_photo_path')->nullable()->after('closed_at')->comment('Foto del efectivo USD al cierre de turno');
            $table->string('close_pos_receipt_photo_path')->nullable()->after('close_usd_cash_photo_path')->comment('Foto del cierre del punto de venta al cerrar turno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cajas_fisicas', function (Blueprint $table): void {
            $table->dropColumn(['close_usd_cash_photo_path', 'close_pos_receipt_photo_path']);
        });
    }
};
