<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Devoluciones de mercancía recibida de más (registradas por Gerencia, revisadas por Administración).
     */
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique()->comment('DEV-{año}-{id}');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete()->comment('Compra con la que llegó la mercancía (opcional)');
            $table->date('received_on')->comment('Fecha en que se recibió la mercancía');
            $table->text('notes')->comment('Nota del gerente');
            $table->string('status', 30)->default('por-revisar')->index()->comment('App\Enums\SupplierReturnStatus');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->date('returned_on')->nullable()->comment('Fecha en que se entregó al proveedor');
            $table->text('return_notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
