<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los precios viven en inventarios por sucursal.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'sale_price',
                'cost_price',
                'tax_rate',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 12, 2)->default(0)->after('net_content_label')->comment('Precio de venta al público');
            $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price')->comment('Costo de adquisición o valoración');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost_price')->comment('Porcentaje de impuesto aplicable (IVA u otro)');
        });
    }
};
