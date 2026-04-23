<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchases', 'payment_due_date')) {
            Schema::table('purchases', function (Blueprint $table): void {
                $table->date('payment_due_date')
                    ->nullable()
                    ->after('supplier_invoice_date')
                    ->comment('Vencimiento del crédito / pago; se usa en cuentas por pagar cuando la compra es a crédito');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'payment_due_date')) {
            Schema::table('purchases', function (Blueprint $table): void {
                $table->dropColumn('payment_due_date');
            });
        }
    }
};
