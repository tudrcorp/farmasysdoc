<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_printers', function (Blueprint $table): void {
            $table->string('mode', 20)->default('desactivada')->after('is_active')->comment('FiscalPrinterMode');
            $table->json('payment_slots')->nullable()->after('mode')
                ->comment('Código de pago Farmadoc → nº de medio de pago programado en la máquina (01-24)');
            $table->json('command_format')->nullable()->after('payment_slots')
                ->comment('Ajustes opcionales del formato de comandos HKA para este firmware');
            $table->timestamp('mode_changed_at')->nullable()->after('command_format');
            $table->string('mode_changed_by')->nullable()->after('mode_changed_at');
        });

        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->boolean('simulation')->default(false)->after('status')
                ->comment('Documento de prueba: el agente arma los comandos sin imprimir');
            $table->index(['fiscal_printer_id', 'simulation', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->dropIndex(['fiscal_printer_id', 'simulation', 'status']);
            $table->dropColumn('simulation');
        });

        Schema::table('fiscal_printers', function (Blueprint $table): void {
            $table->dropColumn(['mode', 'payment_slots', 'command_format', 'mode_changed_at', 'mode_changed_by']);
        });
    }
};
