<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_printers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('physical_cash_box_id')
                ->nullable()
                ->unique()
                ->constrained('cajas_fisicas')
                ->nullOnDelete()
                ->comment('Caja física (cajero) que imprime en esta máquina fiscal');
            $table->string('name', 80);
            $table->string('model', 40)->comment('FiscalPrinterModel');
            $table->string('serial_number', 40)->unique()->comment('Serial impreso en la etiqueta del equipo');
            $table->string('fiscal_registry', 40)->unique()->comment('Nº de registro SENIAT de la máquina fiscal');
            $table->string('connection_port', 40)->nullable()->comment('Puerto local del agente (p. ej. COM3)');
            $table->string('agent_token_hash', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('last_status')->nullable();
            $table->string('agent_version', 40)->nullable();
            $table->string('last_fiscal_number', 20)->nullable();
            $table->string('last_z_number', 20)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_printers');
    }
};
