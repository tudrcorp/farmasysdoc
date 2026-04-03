<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mayor precisión para que el margen % reproduzca el precio de venta con menos error de redondeo.
     */
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->decimal('profit_percentage', 12, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->decimal('profit_percentage', 10, 2)->default(0)->change();
        });
    }
};
