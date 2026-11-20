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
        Schema::table('order_acceptances', function (Blueprint $table) {
            $table->unsignedBigInteger('shipment_authorized_by')->nullable()->after('delivery_note_number');
            $table->foreign('shipment_authorized_by', 'oa_ship_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->string('shipment_authorized_by_name')->nullable()->after('shipment_authorized_by');
            $table->timestamp('shipment_authorized_at')->nullable()->after('shipment_authorized_by_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_acceptances', function (Blueprint $table) {
            $table->dropForeign('oa_ship_user_fk');
            $table->dropColumn([
                'shipment_authorized_by',
                'shipment_authorized_by_name',
                'shipment_authorized_at',
            ]);
        });
    }
};
