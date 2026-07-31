<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('base_salary_usd', 14, 2)->default(0);
            $table->decimal('assignments_usd', 14, 2)->default(0);
            $table->decimal('deductions_usd', 14, 2)->default(0);
            $table->decimal('loans_usd', 14, 2)->default(0);
            $table->decimal('net_usd', 14, 2)->default(0);
            $table->decimal('base_salary_ves', 14, 2)->default(0);
            $table->decimal('assignments_ves', 14, 2)->default(0);
            $table->decimal('deductions_ves', 14, 2)->default(0);
            $table->decimal('loans_ves', 14, 2)->default(0);
            $table->decimal('net_ves', 14, 2)->default(0);
            $table->decimal('bcv_ves_per_usd', 18, 6);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
    }
};
