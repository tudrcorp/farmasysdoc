<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->date('requested_at');
            $table->text('notes');
            $table->string('requested_currency', 8);
            $table->decimal('requested_amount', 14, 2);
            $table->decimal('amount_usd', 14, 2);
            $table->decimal('amount_ves', 14, 2);
            $table->decimal('bcv_ves_per_usd', 16, 6)->nullable();
            $table->decimal('remaining_usd', 14, 2);
            $table->decimal('remaining_ves', 14, 2);
            $table->string('bank_code', 4);
            $table->string('bank_account_type', 20);
            $table->string('bank_account_number', 30);
            $table->string('bank_account_holder');
            $table->string('status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('requested_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_funds');
    }
};
