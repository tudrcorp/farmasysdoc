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
        Schema::create('cajas_fisicas_cierre_reportes', function (Blueprint $table): void {
            $table->id()->comment('Reporte comparativo de un cierre de caja física');
            $table->foreignId('physical_cash_box_id')->comment('Caja física cerrada')->constrained('cajas_fisicas')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Cajero que declaró el cierre')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->comment('Sucursal del turno')->constrained('branches')->nullOnDelete();
            $table->timestamp('opened_at')->comment('Apertura del turno');
            $table->timestamp('closed_at')->comment('Cierre del turno');
            $table->decimal('declared_usd', 14, 2)->default(0)->comment('Efectivo USD declarado por el cajero');
            $table->decimal('declared_ves', 18, 2)->default(0)->comment('Efectivo VES declarado por el cajero');
            $table->decimal('expected_usd', 14, 2)->default(0)->comment('Efectivo USD esperado por el sistema');
            $table->decimal('expected_ves', 18, 2)->default(0)->comment('Efectivo VES esperado por el sistema');
            $table->decimal('difference_usd', 14, 2)->default(0)->comment('Declarado USD menos esperado USD');
            $table->decimal('difference_ves', 18, 2)->default(0)->comment('Declarado VES menos esperado VES');
            $table->decimal('pos_declared_ves', 18, 2)->default(0)->comment('Total POS declarado en bolívares');
            $table->decimal('pos_system_ves', 18, 2)->default(0)->comment('Total POS registrado por el sistema en bolívares');
            $table->decimal('pos_difference_ves', 18, 2)->default(0)->comment('POS declarado menos POS del sistema');
            $table->boolean('has_cash_mismatch')->default(false)->comment('Hay faltante o sobrante en efectivo');
            $table->boolean('has_pos_mismatch')->default(false)->comment('Hay faltante o sobrante en puntos de venta');
            $table->boolean('has_mismatch')->default(false)->comment('El cierre no está cuadrado');
            $table->json('pos_lines')->nullable()->comment('Comparación por banco del punto de venta');
            $table->json('report_snapshot')->nullable()->comment('Snapshot completo del reporte de turno');
            $table->string('pdf_path')->nullable()->comment('PDF persistido del reporte comparativo');
            $table->string('close_usd_cash_photo_path')->nullable()->comment('Foto del efectivo USD al cierre');
            $table->string('close_pos_receipt_photo_path')->nullable()->comment('Foto del cierre de punto de venta');
            $table->timestamp('whatsapp_sent_at')->nullable()->comment('WhatsApp enviado al menos a un destinatario');
            $table->timestamp('email_sent_at')->nullable()->comment('Correo enviado al menos a un destinatario');
            $table->text('whatsapp_error')->nullable()->comment('Último error o motivo de no envío por WhatsApp');
            $table->text('email_error')->nullable()->comment('Último error o motivo de no envío por correo');
            $table->timestamps();

            $table->index(['branch_id', 'closed_at']);
            $table->index(['user_id', 'closed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas_fisicas_cierre_reportes');
    }
};
