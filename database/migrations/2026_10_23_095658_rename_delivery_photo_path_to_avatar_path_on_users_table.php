<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La foto de repartidor pasa a ser la foto de perfil del usuario en todos los paneles
 * (incluida la ficha de pedido que ve la compañía aliada). Se conservan las fotos ya cargadas.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('delivery_photo_path', 'avatar_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('avatar_path', 'delivery_photo_path');
        });
    }
};
