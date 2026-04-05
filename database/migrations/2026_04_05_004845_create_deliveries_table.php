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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->integer('branch_id')->comment('ID de la sucursal')->nullable();
            $table->integer('order_id')->comment('ID de la orden')->nullable();
            $table->integer('user_id')->comment('ID del usuario')->nullable();
            $table->string('delivery_type')->comment('Tipo de entrega')->nullable();
            $table->string('taken_by')->comment('Tomado por')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
