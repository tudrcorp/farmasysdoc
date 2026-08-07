<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('bank_code', 8)->comment('Código bancario de 4 dígitos (catálogo VenezuelanPagoMovilBank)');
            $table->string('account_number', 30)->comment('Número de cuenta (solo dígitos)');
            $table->string('phone', 40)->nullable()->comment('Teléfono asociado a la cuenta / Pago Móvil');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['supplier_id', 'bank_code', 'account_number'],
                'supplier_bank_accounts_supplier_bank_account_unique',
            );
            $table->index(['supplier_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bank_accounts');
    }
};
