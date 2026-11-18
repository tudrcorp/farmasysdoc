<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_held_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('status', 20)->default('paused');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('label', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->json('form_data');
            $table->json('item_lines')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->decimal('estimated_total_usd', 14, 2)->nullable();
            $table->timestamp('paused_at');
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('manager_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamp('discarded_at')->nullable();
            $table->foreignId('discarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('discard_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['status', 'paused_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_held_sales');
    }
};
