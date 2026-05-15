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
        Schema::create('cajas_fisicas', function (Blueprint $table) {
            $table->id()->comment('Identificador de la caja física (efectivo para vueltos)');
            $table->foreignId('user_id')->unique()->comment('Cajero dueño de la caja; relación uno a uno');
            $table->decimal('amount_usd', 14, 2)->default(0)->comment('Monto en efectivo en USD');
            $table->decimal('amount_ves', 18, 2)->default(0)->comment('Monto en efectivo en bolívares (VES)');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas_fisicas');
    }
};
