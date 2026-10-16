<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_z_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_printer_id')->constrained('fiscal_printers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete()
                ->comment('Sucursal de la máquina al capturar el Z (no cambia si la máquina se mueve)');
            $table->foreignId('fiscal_document_id')->nullable()->constrained('fiscal_documents')->restrictOnDelete()
                ->comment('Trabajo del agente que trajo los datos (reporte Z o lectura de memoria)');
            $table->string('printer_serial', 40)->comment('Registro de la máquina fiscal (normalizado, sin guiones)');
            $table->unsignedInteger('z_number');
            $table->date('report_date');
            $table->dateTime('report_datetime')->nullable();
            $table->unsignedInteger('last_invoice_number');
            $table->dateTime('last_invoice_datetime')->nullable();
            $table->unsignedInteger('last_credit_note_number')->default(0);
            $table->unsignedInteger('last_debit_note_number')->default(0);
            $table->unsignedInteger('last_non_fiscal_number')->nullable();

            foreach (['sales', 'credit_notes', 'debit_notes'] as $group) {
                $table->decimal("exempt_{$group}_ves", 16, 2)->default(0);
                $table->decimal("general_base_{$group}_ves", 16, 2)->default(0);
                $table->decimal("general_tax_{$group}_ves", 16, 2)->default(0);
                $table->decimal("reduced_base_{$group}_ves", 16, 2)->default(0);
                $table->decimal("reduced_tax_{$group}_ves", 16, 2)->default(0);
                $table->decimal("additional_base_{$group}_ves", 16, 2)->default(0);
                $table->decimal("additional_tax_{$group}_ves", 16, 2)->default(0);
                $table->decimal("igtf_base_{$group}_ves", 16, 2)->default(0);
                $table->decimal("igtf_tax_{$group}_ves", 16, 2)->default(0);
            }

            $table->decimal('general_rate_percent', 5, 2)->nullable()->comment('Alícuotas programadas en la máquina al leer el Z');
            $table->decimal('reduced_rate_percent', 5, 2)->nullable();
            $table->decimal('additional_rate_percent', 5, 2)->nullable();
            $table->decimal('igtf_rate_percent', 5, 2)->nullable();
            $table->string('source', 24)->comment('FiscalZReportSource');
            $table->char('content_hash', 64)->comment('SHA-256 de los datos fiscales; evidencia de integridad');
            $table->json('raw')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(['printer_serial', 'z_number']);
            $table->index(['branch_id', 'report_date']);
            $table->index(['fiscal_printer_id', 'z_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_z_reports');
    }
};
