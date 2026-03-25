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
            $table->decimal('payment_usd', 12, 2)
                ->nullable()
                ->after('payment_method')
                ->comment('Monto cobrado en dólares (USD)');
            $table->decimal('payment_ves', 14, 2)
                ->nullable()
                ->after('payment_usd')
                ->comment('Monto cobrado en bolívares (VES)');
            $table->string('payment_reference')
                ->nullable()
                ->after('payment_ves')
                ->comment('Referencia (Zelle, transferencias VES, pago móvil, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_usd', 'payment_ves', 'payment_reference']);
        });
    }
};
