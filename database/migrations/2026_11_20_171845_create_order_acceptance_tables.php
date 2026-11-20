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
        Schema::create('order_acceptances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unique('order_id', 'oa_order_unique');
            $table->foreign('order_id', 'oa_order_fk')->references('id')->on('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('partner_company_id')->nullable();
            $table->foreign('partner_company_id', 'oa_partner_fk')->references('id')->on('partner_companies')->nullOnDelete();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->foreign('accepted_by', 'oa_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->string('accepted_by_name')->nullable();
            $table->timestamp('accepted_at');
            $table->decimal('bcv_ves_per_usd', 12, 2);
            $table->decimal('accepted_total_usd', 14, 2)->default(0);
            $table->decimal('accepted_total_ves', 18, 2)->default(0);
            $table->string('delivery_note_number', 32)->unique('oa_note_number_unique');
            $table->timestamps();
        });

        Schema::create('order_acceptance_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_acceptance_id');
            $table->foreign('order_acceptance_id', 'oai_acceptance_fk')->references('id')->on('order_acceptances')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->foreign('order_item_id', 'oai_item_fk')->references('id')->on('order_items')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id', 'oai_product_fk')->references('id')->on('products')->nullOnDelete();
            $table->string('product_name', 500);
            $table->string('barcode')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->boolean('accepted')->default(false);
            $table->text('rejection_note')->nullable();
            $table->decimal('unit_price_usd', 14, 2)->default(0);
            $table->decimal('line_total_usd', 14, 2)->default(0);
            $table->decimal('line_total_ves', 18, 2)->default(0);
            $table->timestamps();

            $table->index('order_acceptance_id', 'oai_acceptance_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_acceptance_items');
        Schema::dropIfExists('order_acceptances');
    }
};
