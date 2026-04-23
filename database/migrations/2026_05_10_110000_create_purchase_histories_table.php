<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_histories', function (Blueprint $table) {
            $table->id();
            $table->string('entry_type', 40)->index()->comment('compra_contado | pago_cuenta_por_pagar');
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('accounts_payable_id')->nullable()->constrained('accounts_payables')->nullOnDelete();

            $table->date('issued_at')->comment('Fecha de emisión factura proveedor');
            $table->date('registered_in_system_date')->comment('Fecha de registro en sistema (compra o contexto documento)');

            $table->string('supplier_invoice_number', 128);
            $table->string('supplier_control_number', 128)->nullable();
            $table->string('supplier_tax_id', 32)->nullable();
            $table->string('supplier_name', 255);

            $table->decimal('purchase_total_usd', 16, 2)->default(0);
            $table->decimal('purchase_total_ves_at_issue', 18, 2)->default(0);
            $table->decimal('total_ves_at_system_registration', 18, 2)->default(0)->comment('Total documento en Bs según tasa BCV del día de registro en sistema');

            $table->string('payment_method', 64)->nullable()->comment('Solo pagos a CxP');
            $table->string('payment_form', 64)->nullable()->comment('Solo pagos a CxP');
            $table->timestamp('paid_at')->nullable();
            $table->decimal('amount_paid_ves', 18, 2)->nullable();
            $table->decimal('amount_paid_usd', 16, 2)->nullable();
            $table->decimal('bcv_rate_at_payment', 16, 2)->nullable();

            $table->text('notes')->nullable();
            $table->string('created_by', 191)->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'entry_type']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_histories');
    }
};
