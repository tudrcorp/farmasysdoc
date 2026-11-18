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
        Schema::table('active_ingredient_formulary_category', function (Blueprint $table) {
            $table->string('presentation', 150)->default('')->after('active_ingredient_id')
                ->comment('Concentración y forma farmacéutica (ej.: 500 mg tabletas)');
            $table->unsignedSmallInteger('sort')->default(0)->after('presentation');
        });

        Schema::table('active_ingredient_formulary_category', function (Blueprint $table) {
            $table->unique(['formulary_category_id', 'active_ingredient_id', 'presentation'], 'formulary_category_presentation_unique');
        });

        Schema::table('active_ingredient_formulary_category', function (Blueprint $table) {
            $table->dropUnique('formulary_category_ingredient_unique');
        });

        Schema::table('formularies', function (Blueprint $table) {
            $table->boolean('requires_prescription')->default(false)->after('is_active')
                ->comment('Los medicamentos del catálogo requieren récipe');
            $table->boolean('requires_authorization')->default(false)->after('requires_prescription')
                ->comment('Los medicamentos del catálogo requieren autorización previa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formularies', function (Blueprint $table) {
            $table->dropColumn(['requires_prescription', 'requires_authorization']);
        });

        Schema::table('active_ingredient_formulary_category', function (Blueprint $table) {
            $table->unique(['formulary_category_id', 'active_ingredient_id'], 'formulary_category_ingredient_unique');
        });

        Schema::table('active_ingredient_formulary_category', function (Blueprint $table) {
            $table->dropUnique('formulary_category_presentation_unique');
            $table->dropColumn(['presentation', 'sort']);
        });
    }
};
