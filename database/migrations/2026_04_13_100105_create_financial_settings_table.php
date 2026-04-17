<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financial_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('default_vat_rate_percent', 6, 2)->default(16)->comment('IVA por defecto en pedidos y compras (%)');
            $table->timestamps();
        });

        DB::table('financial_settings')->insert([
            'id' => 1,
            'default_vat_rate_percent' => 16,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
    }
};
