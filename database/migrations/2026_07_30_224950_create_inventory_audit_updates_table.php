<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audit_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_audit_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('product_sku')->nullable();
            $table->string('product_barcode')->nullable();
            $table->string('product_name');
            $table->string('branch_name');
            $table->decimal('previous_quantity', 15, 3);
            $table->decimal('new_quantity', 15, 3);
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('previous_cost_price', 15, 2);
            $table->decimal('new_cost_price', 15, 2)->nullable();
            $table->boolean('quantity_changed')->default(false);
            $table->boolean('cost_changed')->default(false);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('processed_by_name')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['branch_id', 'processed_at']);
            $table->index('inventory_audit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_updates');
    }
};
