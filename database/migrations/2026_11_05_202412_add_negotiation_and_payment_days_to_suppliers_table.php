<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->text('negotiation_terms')->nullable()->after('payment_terms')
                ->comment('Acuerdos negociados (descuentos, bonificaciones, devoluciones…); solo Administrador');
            $table->unsignedSmallInteger('payment_days')->nullable()->after('negotiation_terms')
                ->comment('Días de crédito para pagar sus facturas (0 = contado); vacío = 30 por defecto; solo Administrador');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['negotiation_terms', 'payment_days']);
        });
    }
};
