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
        Schema::table('plans', function (Blueprint $table) {
            $table->string('premium_mode', 20)
                ->default('tarifa_unica')
                ->after('status')
                ->comment('Modalidad de la tarifa: tarifa_unica, por_rango_edad o retail (sin prima)');
        });

        DB::table('plans')->where('uses_age_ranges', true)->update(['premium_mode' => 'por_rango_edad']);

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('uses_age_ranges');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('uses_age_ranges')->default(false)->after('status')->comment('Si la prima depende del rango de edad');
        });

        DB::table('plans')->where('premium_mode', 'por_rango_edad')->update(['uses_age_ranges' => true]);

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('premium_mode');
        });
    }
};
