<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts_receivables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('status', 40)->default('por_cobrar')->index();
            $table->string('sale_number_snapshot', 128);
            $table->string('client_name_snapshot', 255);
            $table->string('client_document_snapshot', 120)->nullable();
            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->decimal('sale_total_usd', 16, 2)->default(0);
            $table->decimal('paid_equivalent_usd', 16, 2)->default(0);
            $table->decimal('remaining_principal_usd', 16, 2)->default(0);
            $table->decimal('payment_usd_snapshot', 16, 2)->default(0);
            $table->decimal('payment_ves_snapshot', 18, 2)->default(0);
            $table->decimal('bcv_ves_per_usd_snapshot', 18, 6)->nullable();
            $table->decimal('sale_total_ves_reference', 18, 2)->default(0);
            $table->decimal('original_balance_ves', 18, 2)->default(0);
            $table->decimal('current_balance_ves', 18, 2)->default(0);
            $table->timestamp('last_balance_recalculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'due_at']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_receivables');
    }
};
