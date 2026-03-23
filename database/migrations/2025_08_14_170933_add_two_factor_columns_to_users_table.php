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
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->after('password')->nullable()->comment('Secreto TOTP encriptado para autenticación de dos factores');
            $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable()->comment('Códigos de recuperación hasheados (uno por línea o JSON según implementación)');
            $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable()->comment('Fecha en que el usuario confirmó el segundo factor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
