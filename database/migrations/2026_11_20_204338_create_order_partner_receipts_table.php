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
        Schema::create('order_partner_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unique('order_id', 'opr_order_unique');
            $table->foreign('order_id', 'opr_order_fk')->references('id')->on('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('partner_company_id')->nullable();
            $table->foreign('partner_company_id', 'opr_partner_fk')->references('id')->on('partner_companies')->nullOnDelete();
            $table->string('external_number');
            $table->string('external_status', 64);
            $table->string('external_status_label')->nullable();
            $table->string('service_order_number')->nullable();
            $table->string('delivery_note_number')->nullable();
            $table->string('receipt_number')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('received_by')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->text('observations')->nullable();
            $table->json('medications')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::table('partner_uncovered_medicine_connections', function (Blueprint $table) {
            $table->timestamp('receipt_synced_at')->nullable();
            $table->text('receipt_sync_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_uncovered_medicine_connections', function (Blueprint $table) {
            $table->dropColumn(['receipt_synced_at', 'receipt_sync_error']);
        });

        Schema::dropIfExists('order_partner_receipts');
    }
};
