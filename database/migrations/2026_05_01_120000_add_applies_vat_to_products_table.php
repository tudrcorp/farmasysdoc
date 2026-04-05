<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indica si el producto grava IVA al armar líneas de pedido (tasa vía config/orders.php).
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'applies_vat')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('applies_vat')
                ->default(false)
                ->after('discount_percent')
                ->comment('Si es true, en pedidos se calcula IVA sobre la base de la línea con la tasa global');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'applies_vat')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('applies_vat');
        });
    }
};
