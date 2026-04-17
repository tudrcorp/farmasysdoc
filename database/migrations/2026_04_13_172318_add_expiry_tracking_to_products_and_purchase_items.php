<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'requires_expiry_on_purchase')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('requires_expiry_on_purchase')
                    ->default(false)
                    ->after('applies_vat')
                    ->comment('Si en compras se debe indicar vencimiento (mes/año) y generar lote');
            });
        }

        if (Schema::hasTable('purchase_items') && ! Schema::hasColumn('purchase_items', 'lot_expiration_month_year')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->string('lot_expiration_month_year', 7)
                    ->nullable()
                    ->after('notes')
                    ->comment('mm/YYYY capturado en la compra; null si el producto no lleva lote en la línea');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_items') && Schema::hasColumn('purchase_items', 'lot_expiration_month_year')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->dropColumn('lot_expiration_month_year');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'requires_expiry_on_purchase')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('requires_expiry_on_purchase');
            });
        }
    }
};
