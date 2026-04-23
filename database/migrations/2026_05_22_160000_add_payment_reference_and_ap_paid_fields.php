<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_histories', 'payment_reference')) {
            Schema::table('purchase_histories', function (Blueprint $table): void {
                $table->string('payment_reference', 255)
                    ->nullable()
                    ->after('bcv_rate_at_payment')
                    ->comment('Referencia bancaria u operación (opcional)');
            });
        }

        if (! Schema::hasColumn('accounts_payables', 'paid_at')) {
            Schema::table('accounts_payables', function (Blueprint $table): void {
                $table->timestamp('paid_at')
                    ->nullable()
                    ->after('due_at')
                    ->comment('Fecha/hora en que quedó saldada (estado pagado)');
            });
        }

        if (! Schema::hasColumn('accounts_payables', 'payment_reference')) {
            Schema::table('accounts_payables', function (Blueprint $table): void {
                $table->string('payment_reference', 255)
                    ->nullable()
                    ->after('paid_at')
                    ->comment('Referencia del pago que saldó la cuenta (si aplica)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts_payables', 'payment_reference')) {
            Schema::table('accounts_payables', function (Blueprint $table): void {
                $table->dropColumn('payment_reference');
            });
        }
        if (Schema::hasColumn('accounts_payables', 'paid_at')) {
            Schema::table('accounts_payables', function (Blueprint $table): void {
                $table->dropColumn('paid_at');
            });
        }
        if (Schema::hasColumn('purchase_histories', 'payment_reference')) {
            Schema::table('purchase_histories', function (Blueprint $table): void {
                $table->dropColumn('payment_reference');
            });
        }
    }
};
