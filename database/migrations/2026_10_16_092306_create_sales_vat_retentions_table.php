<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_vat_retentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('fiscal_printer_id')->nullable()->constrained('fiscal_printers')->restrictOnDelete()
                ->comment('Máquina fiscal que emitió la factura retenida');
            $table->string('customer_name');
            $table->string('customer_rif', 20);
            $table->string('voucher_number', 14)->comment('Nº de comprobante de retención emitido por el cliente (AAAAMM + 8 dígitos)');
            $table->date('voucher_issued_on');
            $table->date('received_on')->comment('Fecha de recepción: define la quincena del Libro de Ventas en que se declara');
            $table->string('invoice_number', 20);
            $table->date('invoice_date');
            $table->decimal('invoice_total_ves', 16, 2)->nullable();
            $table->decimal('invoice_tax_ves', 16, 2);
            $table->decimal('retention_percent', 5, 2);
            $table->decimal('retained_amount_ves', 16, 2);
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['customer_rif', 'voucher_number']);
            $table->index(['branch_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_vat_retentions');
    }
};
