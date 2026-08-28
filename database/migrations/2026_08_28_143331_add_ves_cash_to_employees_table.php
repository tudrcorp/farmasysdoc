<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('first_half_ves_cash', 18, 2)
                ->default(0)
                ->after('second_half_usd_cash')
                ->comment('Bolívares a pagar en la 1.ª quincena (día 15)');
            $table->decimal('second_half_ves_cash', 18, 2)
                ->default(0)
                ->after('first_half_ves_cash')
                ->comment('Bolívares a pagar en la 2.ª quincena (fin de mes)');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['first_half_ves_cash', 'second_half_ves_cash']);
        });
    }
};
