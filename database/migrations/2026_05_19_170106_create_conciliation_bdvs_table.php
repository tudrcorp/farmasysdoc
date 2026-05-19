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
        Schema::create('conciliation_bdvs', function (Blueprint $table): void {
            $table->id()->comment('Identificador interno de la conciliación BDV');
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('environment', 16)->default('qa');
            $table->string('payer_document', 32);
            $table->string('payer_phone', 32);
            $table->string('destination_phone', 32)->comment('Teléfono destino comercio enviado a BDV');
            $table->string('reference', 64)->index();
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('origin_bank', 16)->nullable();
            $table->boolean('req_ced')->default(false);
            $table->unsignedSmallInteger('bdv_http_status')->nullable();
            $table->string('bdv_code', 32)->nullable()->index();
            $table->string('bdv_message')->nullable();
            $table->json('bdv_payload')->nullable();
            $table->json('bdv_response')->nullable();
            $table->timestamp('conciliated_at')->index();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliation_bdvs');
    }
};
