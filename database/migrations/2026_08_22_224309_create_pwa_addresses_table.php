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
        Schema::create('pwa_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pwa_customer_id')->constrained('pwa_customers')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('address_line');
            $table->string('city');
            $table->string('state');
            $table->string('reference')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['pwa_customer_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pwa_addresses');
    }
};
