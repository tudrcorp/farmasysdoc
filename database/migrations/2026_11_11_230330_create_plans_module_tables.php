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
        Schema::create('plan_services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Código interno del servicio');
            $table->string('name')->unique()->comment('Nombre del servicio');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('formulary_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Código interno de la categoría');
            $table->string('name')->unique()->comment('Nombre de la categoría (maestro común)');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('active_ingredient_formulary_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulary_category_id')->constrained(indexName: 'aifc_formulary_category_fk')->cascadeOnDelete();
            $table->foreignId('active_ingredient_id')->constrained(indexName: 'aifc_active_ingredient_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['formulary_category_id', 'active_ingredient_id'], 'formulary_category_ingredient_unique');
        });

        Schema::create('formularies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Código interno del catálogo');
            $table->string('name')->comment('Nombre del catálogo');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('formulary_formulary_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formulary_category_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['formulary_id', 'formulary_category_id'], 'formulary_category_unique');
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Código interno del plan');
            $table->string('name')->comment('Nombre comercial del plan');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('borrador')->index();
            $table->boolean('uses_age_ranges')->default(false)->comment('Si la prima depende del rango de edad');
            $table->decimal('monthly_premium_usd', 14, 2)->nullable()->comment('Prima mensual por persona (tarifa única) en USD');
            $table->decimal('monthly_premium_ves', 18, 2)->nullable()->comment('Prima mensual por persona (tarifa única) en VES');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plan_age_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('min_age')->comment('Edad mínima (inclusive)');
            $table->unsignedSmallInteger('max_age')->nullable()->comment('Edad máxima (inclusive); vacío = en adelante');
            $table->decimal('monthly_premium_usd', 14, 2)->comment('Prima mensual por persona en USD');
            $table->decimal('monthly_premium_ves', 18, 2)->comment('Prima mensual por persona en VES');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'min_age']);
        });

        Schema::create('plan_plan_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_service_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'plan_service_id']);
        });

        Schema::create('formulary_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formulary_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'formulary_id']);
        });

        Schema::create('partner_company_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'partner_company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_company_plan');
        Schema::dropIfExists('formulary_plan');
        Schema::dropIfExists('plan_plan_service');
        Schema::dropIfExists('plan_age_ranges');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('formulary_formulary_category');
        Schema::dropIfExists('formularies');
        Schema::dropIfExists('active_ingredient_formulary_category');
        Schema::dropIfExists('formulary_categories');
        Schema::dropIfExists('plan_services');
    }
};
