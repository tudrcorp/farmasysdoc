<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('first_half_usd_cash', 14, 2)
                ->default(0)
                ->after('monthly_salary_usd')
                ->comment('USD efectivo a pagar en la 1.ª quincena (día 15)');
            $table->decimal('second_half_usd_cash', 14, 2)
                ->default(0)
                ->after('first_half_usd_cash')
                ->comment('USD efectivo a pagar en la 2.ª quincena (fin de mes)');
        });

        Schema::table('hr_deductions', function (Blueprint $table) {
            $table->string('pay_currency_bucket', 8)
                ->default('ves')
                ->after('amount_usd')
                ->comment('Bolsillo de descuento: usd|ves');
        });

        Schema::table('hr_loans', function (Blueprint $table) {
            $table->string('pay_currency_bucket', 8)
                ->default('ves')
                ->after('amount_usd')
                ->comment('Bolsillo de descuento de cuotas: usd|ves');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->decimal('usd_cash_portion', 14, 2)->default(0)->after('base_salary_usd');
            $table->decimal('ves_portion_usd', 14, 2)->default(0)->after('usd_cash_portion');
            $table->decimal('cash_paid_usd', 14, 2)->default(0)->after('net_ves');
            $table->decimal('cash_paid_ves', 18, 2)->default(0)->after('cash_paid_usd');
        });

        Schema::table('payroll_line_items', function (Blueprint $table) {
            $table->string('pay_currency_bucket', 8)
                ->nullable()
                ->after('amount_ves')
                ->comment('Bolsillo aplicado (deducciones/préstamos)');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_line_items', function (Blueprint $table) {
            $table->dropColumn('pay_currency_bucket');
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn([
                'usd_cash_portion',
                'ves_portion_usd',
                'cash_paid_usd',
                'cash_paid_ves',
            ]);
        });

        Schema::table('hr_loans', function (Blueprint $table) {
            $table->dropColumn('pay_currency_bucket');
        });

        Schema::table('hr_deductions', function (Blueprint $table) {
            $table->dropColumn('pay_currency_bucket');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['first_half_usd_cash', 'second_half_usd_cash']);
        });
    }
};
