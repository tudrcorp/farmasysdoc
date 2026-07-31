<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('concept')->nullable();
            $table->decimal('amount_usd', 14, 2);
            $table->decimal('remaining_usd', 14, 2);
            $table->string('frequency');
            $table->string('installment_mode');
            $table->decimal('fixed_installment_usd', 14, 2)->nullable();
            $table->unsignedInteger('installments_count')->nullable();
            $table->decimal('salary_percentage', 6, 2)->nullable();
            $table->string('status');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_loans');
    }
};
