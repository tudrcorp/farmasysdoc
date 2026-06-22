<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_lot_balances', function (Blueprint $table): void {
            $table->dropForeign(['product_lot_id']);
            $table->dropUnique(['product_lot_id']);
            $table->unique(['branch_id', 'product_lot_id']);
            $table->foreign('product_lot_id')
                ->references('id')
                ->on('product_lots')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lot_balances', function (Blueprint $table): void {
            $table->dropForeign(['product_lot_id']);
            $table->dropUnique(['branch_id', 'product_lot_id']);
            $table->unique('product_lot_id');
            $table->foreign('product_lot_id')
                ->references('id')
                ->on('product_lots')
                ->cascadeOnDelete();
        });
    }
};
