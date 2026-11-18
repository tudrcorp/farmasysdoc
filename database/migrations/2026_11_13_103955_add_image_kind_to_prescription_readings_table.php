<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_readings', function (Blueprint $table): void {
            $table->string('image_kind', 30)->default('recipe')->after('is_prescription')
                ->comment('recipe, payment_proof, product u other');
            $table->text('description')->nullable()->after('image_kind')->comment('Qué muestra la imagen, en pocas palabras');
            $table->json('payment_data')->nullable()->after('description')->comment('Datos del comprobante de pago');
            $table->string('model', 60)->nullable()->after('raw_response')->comment('Modelo que hizo la lectura final');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_readings', function (Blueprint $table): void {
            $table->dropColumn(['image_kind', 'description', 'payment_data', 'model']);
        });
    }
};
