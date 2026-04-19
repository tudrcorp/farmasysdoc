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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'allowed_menu_items')) {
                $table->json('allowed_menu_items')
                    ->nullable()
                    ->after('roles')
                    ->comment('Claves de ítems del menú permitidos para el usuario (solo panel Farmaadmin).');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'allowed_menu_items')) {
                $table->dropColumn('allowed_menu_items');
            }
        });
    }
};
