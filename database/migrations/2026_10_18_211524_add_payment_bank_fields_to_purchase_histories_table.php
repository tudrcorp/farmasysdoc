<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_histories', function (Blueprint $table) {
            $table->string('payment_bank_account_type', 20)
                ->nullable()
                ->after('bcv_rate_at_payment')
                ->comment('Tipo de cuenta de origen: corriente, ahorro, electronica, extranjera');
            $table->string('payment_bank_code', 4)
                ->nullable()
                ->after('payment_bank_account_type')
                ->comment('Código del banco venezolano de origen (cuentas corriente/ahorro)');
            $table->string('payment_bank_name', 120)
                ->nullable()
                ->after('payment_bank_code')
                ->comment('Nombre del banco escrito a mano (cuentas electrónicas/extranjeras)');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_histories', function (Blueprint $table) {
            $table->dropColumn([
                'payment_bank_account_type',
                'payment_bank_code',
                'payment_bank_name',
            ]);
        });
    }
};
