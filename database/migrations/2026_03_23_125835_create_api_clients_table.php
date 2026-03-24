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
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre del aliado o sistema integrador');
            $table->string('token_hash', 64)->unique()->comment('Hash SHA-256 del token Bearer');
            $table->boolean('is_active')->default(true)->comment('Indica si el cliente puede consumir la API');
            $table->timestamp('last_used_at')->nullable()->comment('Último uso exitoso del token');
            $table->json('allowed_ips')->nullable()->comment('IPs permitidas para consumir la API (opcional)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
