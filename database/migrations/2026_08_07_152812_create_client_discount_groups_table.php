<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_discount_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('client_discount_group_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_discount_group_id')
                ->constrained('client_discount_groups')
                ->cascadeOnDelete();
            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique('client_id');
            $table->unique(['client_discount_group_id', 'client_id'], 'client_discount_group_client_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_discount_group_client');
        Schema::dropIfExists('client_discount_groups');
    }
};
