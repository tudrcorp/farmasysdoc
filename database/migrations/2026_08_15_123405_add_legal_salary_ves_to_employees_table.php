<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('legal_salary_ves', 16, 2)
                ->nullable()
                ->after('monthly_salary_usd')
                ->comment('Sueldo de ley mensual en bolívares; independiente del sueldo en USD');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('legal_salary_ves');
        });
    }
};
