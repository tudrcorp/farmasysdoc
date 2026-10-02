<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Clave de idempotencia enviada al agente');
            $table->foreignId('fiscal_printer_id')->constrained('fiscal_printers')->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->string('type', 20)->comment('FiscalDocumentType');
            $table->string('status', 24)->comment('FiscalDocumentStatus');
            $table->json('payload');
            $table->decimal('expected_total_ves', 16, 2)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('printer_counter_before', 20)->nullable()
                ->comment('Último nº fiscal leído por el agente antes de imprimir (conciliación)');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->string('fiscal_number', 20)->nullable();
            $table->string('printer_serial', 40)->nullable();
            $table->string('z_number', 20)->nullable();
            $table->timestamp('printer_datetime')->nullable();
            $table->decimal('printer_total_ves', 16, 2)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamps();

            $table->unique(['sale_id', 'type']);
            $table->index(['fiscal_printer_id', 'status', 'id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
