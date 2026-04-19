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
        Schema::table('rols', function (Blueprint $table) {
            if (! Schema::hasColumn('rols', 'allowed_menu_items')) {
                $table->json('allowed_menu_items')->nullable()->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rols', function (Blueprint $table) {
            if (Schema::hasColumn('rols', 'allowed_menu_items')) {
                $table->dropColumn('allowed_menu_items');
            }
        });
    }
};
