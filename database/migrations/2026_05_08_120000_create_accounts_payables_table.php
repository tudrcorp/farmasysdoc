<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->unique()->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('issued_at')->comment('Fecha de emisión de la factura del proveedor');
            $table->date('due_at')->nullable()->comment('Fecha de vencimiento estimada del crédito');
            $table->string('supplier_invoice_number', 128);
            $table->string('supplier_control_number', 128)->nullable();
            $table->string('supplier_tax_id', 32)->nullable()->comment('RIF del proveedor');
            $table->string('supplier_name', 255);
            $table->decimal('purchase_total_usd', 16, 2)->default(0);
            $table->decimal('purchase_total_ves_at_issue', 18, 2)->default(0)->comment('Total en Bs según tasa BCV de la fecha de emisión');
            $table->decimal('original_balance_ves', 18, 2)->default(0)->comment('Saldo en Bs al día de registro en sistema (tasa BCV de esa fecha)');
            $table->decimal('current_balance_ves', 18, 2)->default(0)->comment('Saldo en Bs revalorizado con tasa BCV del día (tarea programada)');
            $table->timestamp('last_balance_recalculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'due_at']);
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_payables');
    }
};
