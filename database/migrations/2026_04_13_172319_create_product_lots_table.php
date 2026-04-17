<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_lots')) {
            return;
        }

        Schema::create('product_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('purchase_item_id')
                ->unique()
                ->constrained('purchase_items')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('supplier_invoice_number', 255)
                ->comment('N° de factura del proveedor al registrar el lote (o referencia de OC si no hay factura)');
            $table->string('expiration_month_year', 7)
                ->comment('Vencimiento del lote en formato mm/YYYY');
            $table->timestamps();

            $table->index(['product_id', 'supplier_invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lots');
    }
};
