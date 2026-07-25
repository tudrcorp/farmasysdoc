<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_books')) {
            return;
        }

        Schema::create('purchase_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->unsignedBigInteger('voucher_number')->comment('Número de comprobante correlativo global');
            $table->string('retention_agent_name');
            $table->string('retention_agent_rif');
            $table->string('tax_period', 7)->comment('Periodo de imposición yyyy/mm');
            $table->text('retention_agent_address');
            $table->date('issue_date')->nullable();
            $table->string('supplier_name');
            $table->string('supplier_rif');
            $table->text('supplier_address')->nullable();
            $table->unsignedInteger('operation_number')->comment('Correlativo 1..n por tax_period');
            $table->date('invoice_date')->nullable();
            $table->string('invoice_number');
            $table->string('invoice_control_number')->nullable();
            $table->unsignedInteger('operation_class')->comment('Correlativo del periodo (igual a operation_number)');
            $table->string('affected_control_number')->nullable();
            $table->decimal('invoice_total_ves', 14, 2)->default(0);
            $table->decimal('purchases_without_vat_credit', 14, 2)->nullable();
            $table->decimal('taxable_base_ves', 14, 2)->default(0);
            $table->decimal('vat_rate_percent', 6, 2)->default(0);
            $table->decimal('tax_caused_ves', 14, 2)->default(0);
            $table->decimal('tax_retained_ves', 14, 2)->default(0);
            $table->decimal('bcv_rate_at_invoice', 12, 8)->nullable();
            $table->decimal('seniat_retention_percent', 6, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique('purchase_id');
            $table->unique('voucher_number');
            $table->unique(['tax_period', 'operation_number']);
            $table->index('tax_period');
            $table->index('invoice_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_books');
    }
};
