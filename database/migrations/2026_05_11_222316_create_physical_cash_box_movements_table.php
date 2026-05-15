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
        Schema::create('cajas_fisicas_movimientos', function (Blueprint $table): void {
            $table->id()->comment('Movimiento de caja física para conciliación (vueltos, etc.)');
            $table->foreignId('physical_cash_box_id')->comment('Caja física del cajero')->constrained('cajas_fisicas')->cascadeOnDelete();
            $table->foreignId('sale_id')->comment('Venta asociada')->constrained('sales')->cascadeOnDelete();
            $table->string('kind', 64)->default('efectivo_usd_vuelto')->index()->comment('Tipo de movimiento para reportes');
            $table->decimal('client_bill_usd', 14, 2)->comment('Billete o efectivo USD del cliente');
            $table->decimal('document_total_usd', 14, 2)->comment('Total cobrado en USD');
            $table->decimal('change_on_bill_usd', 14, 2)->comment('Vuelto en USD sobre el billete (billete − total)');
            $table->decimal('change_on_bill_ves', 18, 2)->nullable()->comment('Equivalente VES del vuelto sobre billete');
            $table->decimal('drawer_out_usd', 14, 2)->default(0)->comment('USD retirados de la caja para vueltos');
            $table->decimal('final_change_usd', 14, 2)->comment('Vuelto restante en USD');
            $table->decimal('final_change_ves', 18, 2)->nullable()->comment('Vuelto restante en VES');
            $table->decimal('bcv_ves_per_usd', 18, 6)->nullable()->comment('Tasa Bs./USD usada en el cálculo');
            $table->json('meta')->nullable()->comment('Detalle extendido o versión futura del payload');
            $table->string('created_by')->nullable()->comment('Usuario que registró el movimiento');
            $table->timestamps();

            $table->unique('sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas_fisicas_movimientos');
    }
};
