<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precio directo opcional del producto (visible según permiso de rol).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('direct_price', 12, 2)
                ->nullable()
                ->after('sale_price')
                ->comment('Precio directo opcional del producto');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('direct_price');
        });
    }
};
