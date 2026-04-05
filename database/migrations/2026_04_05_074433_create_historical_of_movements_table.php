<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historical_of_movements', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->decimal('total_quantity_products', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->decimal('remaining_credit', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_of_movements');
    }
};
