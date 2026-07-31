<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_loan_id')->constrained('hr_loans')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->decimal('amount_usd', 14, 2);
            $table->date('period_date')->nullable();
            $table->foreignId('payroll_line_id')->nullable()->constrained('payroll_lines')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['hr_loan_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_loan_installments');
    }
};
