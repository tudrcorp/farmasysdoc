<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Flexible (fondo de consumo):
 * - partner_plan_affiliates: clientes afiliados a un aliado y a uno de sus planes.
 * - partner_agreement_sales / _items: ventas por convenio hechas en caja (no son ventas fiscales:
 *   emiten nota de entrega a nombre del afiliado y descuentan inventario).
 * - partner_fund_movements: libro del fondo (consumos, devoluciones por anulación y recargas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_plan_affiliates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_company_plan_id')->constrained('partner_company_plan')->cascadeOnDelete();
            $table->foreignId('partner_company_id')->constrained('partner_companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('source', 20);
            $table->string('validated_with')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['partner_company_plan_id', 'client_id'], 'partner_plan_affiliates_assignment_client_unique');
            $table->index(['client_id', 'is_active']);
            $table->index(['partner_company_id', 'is_active']);
        });

        Schema::create('partner_agreement_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('partner_company_id')->constrained('partner_companies');
            $table->foreignId('plan_id')->constrained('plans');
            $table->foreignId('partner_company_plan_id')->constrained('partner_company_plan');
            $table->foreignId('partner_plan_affiliate_id')->constrained('partner_plan_affiliates');
            $table->foreignId('client_id')->constrained('clients');
            $table->string('client_name');
            $table->string('client_document', 60)->nullable();
            $table->string('client_phone', 60)->nullable();
            $table->string('client_email')->nullable();
            $table->string('status', 20)->default('completada');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('bcv_ves_per_usd', 14, 4)->nullable();
            $table->decimal('total_ves', 16, 2)->nullable();
            $table->decimal('fund_balance_after_usd', 14, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('sold_at');
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('whatsapped_at')->nullable();
            $table->timestamps();

            $table->index(['partner_company_id', 'sold_at']);
            $table->index(['partner_company_plan_id', 'status']);
            $table->index(['branch_id', 'sold_at']);
            $table->index(['client_id', 'sold_at']);
        });

        Schema::create('partner_agreement_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_agreement_sale_id')->constrained('partner_agreement_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('inventory_id')->nullable()->constrained('inventories')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('partner_fund_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_company_plan_id')->constrained('partner_company_plan')->cascadeOnDelete();
            $table->foreignId('partner_company_id')->constrained('partner_companies')->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('amount_usd', 14, 2);
            $table->decimal('amount_ves', 16, 2)->nullable();
            $table->decimal('bcv_ves_per_usd', 14, 4)->nullable();
            $table->decimal('balance_after_usd', 14, 2);
            $table->foreignId('partner_agreement_sale_id')->nullable()->constrained('partner_agreement_sales')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['partner_company_plan_id', 'created_at']);
            $table->index(['partner_company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_fund_movements');
        Schema::dropIfExists('partner_agreement_sale_items');
        Schema::dropIfExists('partner_agreement_sales');
        Schema::dropIfExists('partner_plan_affiliates');
    }
};
