<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('first_half_pay_currency', 8)
                ->default('ves')
                ->after('monthly_salary_usd')
                ->comment('Moneda de pago de la 1.ª quincena: usd|ves');
            $table->string('second_half_pay_currency', 8)
                ->default('ves')
                ->after('first_half_pay_currency')
                ->comment('Moneda de pago de la 2.ª quincena: usd|ves');
        });

        DB::table('employees')
            ->where('first_half_usd_cash', '>', 0)
            ->update(['first_half_pay_currency' => 'usd']);

        DB::table('employees')
            ->where('second_half_usd_cash', '>', 0)
            ->update(['second_half_pay_currency' => 'usd']);

        foreach (DB::table('employees')->select([
            'id',
            'monthly_salary_usd',
            'first_half_pay_currency',
            'second_half_pay_currency',
        ])->cursor() as $employee) {
            $base = round((float) $employee->monthly_salary_usd / 2, 2);

            DB::table('employees')->where('id', $employee->id)->update([
                'first_half_usd_cash' => $employee->first_half_pay_currency === 'usd' ? $base : 0,
                'second_half_usd_cash' => $employee->second_half_pay_currency === 'usd' ? $base : 0,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['first_half_pay_currency', 'second_half_pay_currency']);
        });
    }
};
