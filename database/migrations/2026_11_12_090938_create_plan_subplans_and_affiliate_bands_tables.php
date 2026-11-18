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
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_subplans')->default(false)->after('premium_mode')
                ->comment('Si el plan (modalidad por cantidad de afiliados) se divide en subplanes');
        });

        Schema::create('plan_subplans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique()->comment('Clave estable para la estructura de costos del formulario');
            $table->string('name')->comment('Nombre del subplan (ej. Plan Agudos)');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'name']);
        });

        Schema::create('plan_service_plan_subplan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_subplan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_service_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['plan_subplan_id', 'plan_service_id'], 'plan_subplan_service_unique');
        });

        Schema::create('formulary_plan_subplan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_subplan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formulary_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['plan_subplan_id', 'formulary_id'], 'plan_subplan_formulary_unique');
        });

        Schema::create('plan_affiliate_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_affiliates')->comment('Afiliados desde (inclusive)');
            $table->unsignedInteger('max_affiliates')->nullable()->comment('Afiliados hasta (inclusive); null = o más');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'min_affiliates']);
        });

        Schema::create('plan_affiliate_band_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_affiliate_band_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_subplan_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('Subplan de la columna; null cuando el plan no maneja subplanes');
            $table->decimal('price_usd', 10, 2)->comment('USD por afiliado al mes');
            $table->timestamps();

            $table->unique(['plan_affiliate_band_id', 'plan_subplan_id'], 'band_subplan_price_unique');
        });

        Schema::table('partner_company_plan', function (Blueprint $table) {
            $table->foreignId('plan_subplan_id')->nullable()->after('partner_company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('affiliates_count')->nullable()->after('plan_subplan_id')
                ->comment('Afiliados contratados (planes por cantidad de afiliados)');
            $table->decimal('agreed_rate_usd', 10, 2)->nullable()->after('affiliates_count')
                ->comment('Tarifa acordada en USD por afiliado al mes (fija al contratar)');
            $table->string('agreed_band_label', 60)->nullable()->after('agreed_rate_usd')
                ->comment('Tramo del baremo que aplicó al contratar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_company_plan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_subplan_id');
            $table->dropColumn(['affiliates_count', 'agreed_rate_usd', 'agreed_band_label']);
        });

        Schema::dropIfExists('plan_affiliate_band_prices');
        Schema::dropIfExists('plan_affiliate_bands');
        Schema::dropIfExists('formulary_plan_subplan');
        Schema::dropIfExists('plan_service_plan_subplan');
        Schema::dropIfExists('plan_subplans');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_subplans');
        });
    }
};
