<?php

use App\Enums\EmployeeExitStatus;
use App\Enums\EmployeeSettlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('severance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('utilities_days_per_year')->default(30);
            $table->unsignedSmallInteger('vacation_base_days')->default(15);
            $table->unsignedSmallInteger('vacation_max_days')->default(30);
            $table->unsignedSmallInteger('vacation_bonus_base_days')->default(15);
            $table->unsignedSmallInteger('vacation_bonus_max_days')->default(30);
            $table->timestamps();
        });

        Schema::create('employee_salary_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('monthly_salary_usd', 12, 2);
            $table->decimal('legal_salary_ves', 14, 2)->nullable();
            $table->date('effective_on');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_on']);
        });

        Schema::create('employee_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('reason', 30);
            $table->date('last_work_day');
            $table->string('status', 20)->default(EmployeeExitStatus::Open->value)->index();
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_exit_id')->constrained('employee_exits')->restrictOnDelete();
            $table->string('status', 20)->default(EmployeeSettlementStatus::Draft->value)->index();
            $table->date('hired_at');
            $table->date('last_work_day');
            $table->string('service_label', 80);
            $table->decimal('monthly_salary_usd', 12, 2);
            $table->decimal('normal_daily_usd', 12, 4);
            $table->decimal('integral_daily_usd', 12, 4);
            $table->json('parameters');
            $table->decimal('guarantee_accrued_usd', 12, 2)->default(0);
            $table->decimal('accrued_interest_usd', 12, 2)->default(0);
            $table->decimal('advances_usd', 12, 2)->default(0);
            $table->decimal('utilities_paid_usd', 12, 2)->default(0);
            $table->decimal('pending_vacation_days', 6, 2)->default(0);
            $table->decimal('pending_bonus_days', 6, 2)->default(0);
            $table->decimal('unpaid_salary_days', 6, 2)->default(0);
            $table->decimal('total_assignments_usd', 14, 2)->default(0);
            $table->decimal('total_deductions_usd', 14, 2)->default(0);
            $table->decimal('net_usd', 14, 2)->default(0);
            $table->decimal('bcv_ves_per_usd', 16, 6)->nullable();
            $table->decimal('net_ves', 18, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 60)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_settlement_id')->constrained('employee_settlements')->cascadeOnDelete();
            $table->string('concept', 40);
            $table->string('type', 20);
            $table->string('label', 160);
            $table->text('formula');
            $table->decimal('calculated_usd', 14, 2);
            $table->decimal('amount_usd', 14, 2);
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        DB::table('employees')->orderBy('id')->each(function (object $employee): void {
            DB::table('employee_salary_changes')->insert([
                'employee_id' => $employee->id,
                'monthly_salary_usd' => $employee->monthly_salary_usd ?? 0,
                'legal_salary_ves' => $employee->legal_salary_ves,
                'effective_on' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_settlement_lines');
        Schema::dropIfExists('employee_settlements');
        Schema::dropIfExists('employee_exits');
        Schema::dropIfExists('employee_salary_changes');
        Schema::dropIfExists('severance_settings');
    }
};
