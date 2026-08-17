<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('code', 32)->comment('Código del punto de venta / terminal');
            $table->string('bank_code', 8)->comment('Código bancario de 4 dígitos (catálogo VenezuelanPagoMovilBank)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['bank_code', 'code'], 'pos_terminals_bank_code_unique');
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminals');
    }
};
