<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fefo_pos_alert_logs')) {
            return;
        }

        Schema::create('fefo_pos_alert_logs', function (Blueprint $table): void {
            $table->id()->comment('Registro de alerta FEFO emitida en caja');
            $table->foreignId('branch_id')->comment('Sucursal')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('Cajero que recibió la alerta')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->comment('Producto alertado')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_lot_id')->comment('Lote FEFO sugerido')->constrained('product_lots')->cascadeOnDelete();
            $table->string('product_code', 128)->comment('Código del producto al momento de la alerta');
            $table->string('product_name')->comment('Nombre del producto al momento de la alerta');
            $table->string('expiration_month_year', 7)->comment('Vencimiento del lote mm/YYYY');
            $table->string('severity', 16)->comment('critical | warning');
            $table->unsignedSmallInteger('days_until_expiry')->comment('Días hasta vencimiento al emitir la alerta');
            $table->decimal('quantity_in_lot', 12, 3)->comment('Unidades en el lote al emitir la alerta');
            $table->string('supplier_invoice_number', 255)->nullable()->comment('Factura proveedor del lote');
            $table->timestamp('notified_at')->comment('Momento en que el sistema mostró la alerta en caja');
            $table->foreignId('sale_id')->nullable()->comment('Venta vinculada tras completar la operación')->constrained('sales')->nullOnDelete();
            $table->string('sale_number', 64)->nullable()->comment('Número de venta vinculada');
            $table->decimal('quantity_sold', 12, 3)->nullable()->comment('Cantidad vendida del producto en la venta vinculada');
            $table->timestamp('sold_at')->nullable()->comment('Fecha/hora de la venta vinculada');
            $table->timestamps();

            $table->index(['branch_id', 'notified_at']);
            $table->index(['user_id', 'notified_at']);
            $table->index(['product_id', 'notified_at']);
            $table->index(['sale_id']);
            $table->index(['notified_at', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fefo_pos_alert_logs');
    }
};
