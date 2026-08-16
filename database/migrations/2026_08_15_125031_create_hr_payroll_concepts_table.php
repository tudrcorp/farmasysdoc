<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16)->comment('assignment|deduction');
            $table->string('application', 16)->comment('legal|business');
            $table->string('behavior', 16)->nullable()->comment('fixed; vacío por defecto');
            $table->decimal('amount', 16, 2);
            $table->string('currency', 8)->default('ves')->comment('usd|ves');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_concepts');
    }
};
