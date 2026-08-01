<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('bank_account_number', 30)
                ->nullable()
                ->after('address')
                ->comment('Número de cuenta bancaria');
            $table->string('bank_code', 8)
                ->nullable()
                ->after('bank_account_number')
                ->comment('Código del banco (ej. 0102)');
            $table->string('bank_account_type', 20)
                ->nullable()
                ->after('bank_code')
                ->comment('Tipo de cuenta: corriente|ahorro');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['bank_account_number', 'bank_code', 'bank_account_type']);
        });
    }
};
