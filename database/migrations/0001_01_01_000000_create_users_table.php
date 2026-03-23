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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('Identificador único del usuario');
            $table->string('name')->comment('Nombre completo del usuario');
            $table->string('email')->unique()->comment('Correo electrónico (inicio de sesión, único)');
            $table->timestamp('email_verified_at')->nullable()->comment('Fecha de verificación del correo');
            $table->string('password')->comment('Contraseña hasheada');
            $table->rememberToken()->comment('Token para sesión "recordarme"');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Correo para el que se solicitó el restablecimiento');
            $table->string('token')->comment('Token de un solo uso para restablecer contraseña');
            $table->timestamp('created_at')->nullable()->comment('Momento de generación del token');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('ID de sesión del driver de sesiones');
            $table->foreignId('user_id')->nullable()->index()->comment('Usuario autenticado asociado a la sesión');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del cliente');
            $table->text('user_agent')->nullable()->comment('User-Agent del navegador o cliente');
            $table->longText('payload')->comment('Datos serializados de la sesión');
            $table->integer('last_activity')->index()->comment('Última actividad (timestamp Unix)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
