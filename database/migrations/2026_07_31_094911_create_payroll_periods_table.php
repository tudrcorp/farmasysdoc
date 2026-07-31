<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('period_number');
            $table->decimal('bcv_ves_per_usd', 18, 6)->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_assignments_usd', 14, 2)->default(0);
            $table->decimal('total_assignments_ves', 14, 2)->default(0);
            $table->decimal('total_deductions_usd', 14, 2)->default(0);
            $table->decimal('total_deductions_ves', 14, 2)->default(0);
            $table->decimal('total_loans_usd', 14, 2)->default(0);
            $table->decimal('total_loans_ves', 14, 2)->default(0);
            $table->decimal('total_payable_usd', 14, 2)->default(0);
            $table->decimal('total_payable_ves', 14, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'period_number']);
            $table->unique(['period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
