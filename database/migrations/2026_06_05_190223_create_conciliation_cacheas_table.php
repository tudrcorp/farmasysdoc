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
        Schema::create('conciliation_cacheas', function (Blueprint $table): void {
            $table->id()->comment('Identificador interno de conciliación Cachea');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('sale_number', 64)->index();
            $table->decimal('sale_total', 14, 2)->comment('Total documentado de la venta (USD)');
            $table->decimal('cachea_paid_amount', 14, 2)->comment('Monto pagado por el cliente vía Cachea (USD)');
            $table->decimal('remainder', 14, 2)->comment('Resto = venta total − pago Cachea (USD pendiente Cachea)');
            $table->string('complement_payment_method', 32)->nullable()->comment('Forma de pago del resto en caja, si aplica');
            $table->string('reference', 255)->nullable()->comment('Referencia del complemento o transacción');
            $table->timestamp('recorded_at')->index();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliation_cacheas');
    }
};
