<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('payroll_line_id')->nullable()->constrained('payroll_lines')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('worker_name');
            $table->string('national_id')->nullable();
            $table->string('month_label');
            $table->unsignedSmallInteger('year');
            $table->string('branch_name')->nullable();
            $table->string('branch_address')->nullable();
            $table->decimal('legal_salary_monthly_ves', 16, 2)->default(0);
            $table->decimal('legal_salary_biweekly_ves', 16, 2)->default(0);
            $table->decimal('assignments_ves', 16, 2)->default(0);
            $table->decimal('deductions_ves', 16, 2)->default(0);
            $table->decimal('total_ves', 16, 2)->default(0);
            $table->json('items');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('whatsapped_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_receipts');
    }
};
