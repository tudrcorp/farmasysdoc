<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->decimal('remaining_principal_usd', 16, 2)
                ->nullable()
                ->after('purchase_total_usd')
                ->comment('Principal USD pendiente; el saldo en Bs se deriva con la tasa BCV del día');
        });

        DB::table('accounts_payables')->update([
            'remaining_principal_usd' => DB::raw('purchase_total_usd'),
        ]);
    }

    public function down(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->dropColumn('remaining_principal_usd');
        });
    }
};
