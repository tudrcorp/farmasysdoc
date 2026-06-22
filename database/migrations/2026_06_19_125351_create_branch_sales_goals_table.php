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
        Schema::create('branch_sales_goals', function (Blueprint $table): void {
            $table->id()->comment('Identificador de la meta mensual');
            $table->unsignedSmallInteger('period_year')->comment('Año calendario de la meta (ej. 2026)');
            $table->unsignedTinyInteger('period_month')->comment('Mes calendario de la meta (1–12)');
            $table->boolean('is_global')->default(false)->comment('true: meta global de la empresa; false: meta de una sucursal');
            $table->foreignId('branch_id')
                ->nullable()
                ->comment('Sucursal objetivo; null cuando is_global es true')
                ->constrained('branches')
                ->nullOnDelete();
            $table->decimal('goal_usd', 14, 2)->comment('Meta de ventas en dólares (USD) para el periodo');
            $table->string('created_by')->nullable()->comment('Usuario que registró la meta');
            $table->string('updated_by')->nullable()->comment('Usuario que actualizó la meta por última vez');
            $table->timestamps();

            $table->unique(
                ['period_year', 'period_month', 'is_global', 'branch_id'],
                'branch_sales_goals_period_scope_unique',
            );
            $table->index(['period_year', 'period_month'], 'branch_sales_goals_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_sales_goals');
    }
};
