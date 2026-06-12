<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_lot_balances')) {
            return;
        }

        Schema::create('inventory_lot_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('product_lot_id')
                ->unique()
                ->constrained('product_lots')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('quantity_remaining', 12, 3)->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lot_balances');
    }
};
