<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_payables', 'payment_proof_path')) {
                $table->string('payment_proof_path', 512)
                    ->nullable()
                    ->after('payment_reference')
                    ->comment('Ruta del comprobante de pago (imagen o PDF)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_payables', 'payment_proof_path')) {
                $table->dropColumn('payment_proof_path');
            }
        });
    }
};
