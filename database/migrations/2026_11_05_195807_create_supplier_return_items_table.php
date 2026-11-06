<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete()->comment('Vacío si el producto no está en el catálogo');
            $table->foreignId('purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('product_name');
            $table->decimal('quantity_invoiced', 12, 3)->comment('Cantidad pedida / facturada');
            $table->decimal('quantity_received', 12, 3)->comment('Cantidad recibida físicamente');
            $table->decimal('quantity_excess', 12, 3)->comment('Recibida − facturada (lo que se devuelve)');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
    }
};
