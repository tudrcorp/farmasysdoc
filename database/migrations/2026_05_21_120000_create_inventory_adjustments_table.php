<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('unit_cost_snapshot', 15, 2)->nullable();
            $table->string('reason', 64)->index();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
