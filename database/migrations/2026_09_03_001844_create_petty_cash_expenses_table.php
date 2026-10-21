<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->timestamp('spent_at');
            $table->text('description');
            $table->string('currency', 8);
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_usd', 14, 2);
            $table->decimal('amount_ves', 14, 2);
            $table->decimal('bcv_ves_per_usd', 16, 6)->nullable();
            $table->string('receipt_path');
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('spent_at');
            $table->index(['petty_cash_fund_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expenses');
    }
};
