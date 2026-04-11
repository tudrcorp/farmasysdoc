<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Porcentaje de descuento e IVA por línea (capturados en el formulario de compra).
     */
    public function up(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        Schema::table('purchase_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_items', 'line_discount_percent')) {
                $table->decimal('line_discount_percent', 5, 2)
                    ->default(0)
                    ->after('unit_cost')
                    ->comment('Descuento % sobre el costo bruto de la línea');
            }
            if (! Schema::hasColumn('purchase_items', 'line_vat_percent')) {
                $table->decimal('line_vat_percent', 5, 2)
                    ->default(0)
                    ->after('line_discount_percent')
                    ->comment('IVA % aplicado sobre el subtotal neto de la línea');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        Schema::table('purchase_items', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_items', 'line_vat_percent')) {
                $table->dropColumn('line_vat_percent');
            }
            if (Schema::hasColumn('purchase_items', 'line_discount_percent')) {
                $table->dropColumn('line_discount_percent');
            }
        });
    }
};
