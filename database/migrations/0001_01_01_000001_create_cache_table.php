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
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Clave única de la entrada en caché');
            $table->mediumText('value')->comment('Valor serializado almacenado');
            $table->integer('expiration')->index()->comment('Timestamp Unix de expiración de la entrada');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Clave del recurso bloqueado (locks distribuidos)');
            $table->string('owner')->comment('Identificador del proceso que posee el bloqueo');
            $table->integer('expiration')->index()->comment('Timestamp Unix hasta el cual es válido el bloqueo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
