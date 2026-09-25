<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('requester_name');
            $table->string('requester_document');
            $table->string('requester_phone');
            $table->string('requester_email');
            $table->string('adjustment_kind')->default('none');
            $table->decimal('adjustment_percent', 8, 2)->default(0);
            $table->decimal('subtotal_usd', 14, 2)->default(0);
            $table->decimal('total_usd', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('whatsapped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('medication_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_quote_id')->constrained('medication_quotes')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price_usd', 14, 2);
            $table->decimal('line_total_usd', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_quote_lines');
        Schema::dropIfExists('medication_quotes');
    }
};
