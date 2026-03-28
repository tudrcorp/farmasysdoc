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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('bcv_ves_per_usd', 18, 6)
                ->nullable()
                ->after('payment_ves')
                ->comment('Tasa aplicada en la venta: bolívares por 1 USD (API BCV o manual en POS)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('bcv_ves_per_usd');
        });
    }
};
