<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_concept_payroll_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_payroll_concept_id')
                ->constrained('hr_payroll_concepts')
                ->cascadeOnDelete();
            $table->foreignId('payroll_period_id')
                ->constrained('payroll_periods')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['hr_payroll_concept_id', 'payroll_period_id'],
                'hr_concept_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_concept_payroll_period');
    }
};
