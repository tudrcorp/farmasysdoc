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
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'expiration_date')) {
                $table->date('expiration_date')
                    ->nullable()
                    ->after('storage_conditions')
                    ->comment('Fecha de vencimiento o caducidad de referencia en catálogo (opcional)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'expiration_date')) {
                $table->dropColumn('expiration_date');
            }
        });
    }
};
