<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audit_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('system_cost_price', 15, 2)->default(0);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('new_cost_price', 15, 2)->nullable();
            $table->decimal('quantity_delta', 15, 3)->nullable();
            $table->boolean('cost_changed')->default(false);
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_audit_id', 'inventory_id']);
            $table->index(['inventory_audit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_lines');
    }
};
