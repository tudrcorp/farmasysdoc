<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca a los usuarios del panel de aliados que siguen con la clave estándar asignada por
 * Farmadoc (se apaga sola cuando el usuario la cambia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('partner_uses_default_password')->default(false)->after('partner_last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('partner_uses_default_password');
        });
    }
};
