<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_fixed_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->string('category', 40);
            $table->string('description');
            $table->string('currency', 8);
            $table->decimal('amount', 16, 2);
            $table->decimal('amount_usd', 16, 2);
            $table->decimal('amount_ves', 18, 2);
            $table->decimal('bcv_ves_per_usd', 18, 6)->nullable();
            $table->string('receipt_path')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_fixed_expenses');
    }
};
