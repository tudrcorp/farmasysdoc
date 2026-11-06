<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_partner_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'partner_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_partner_company');
    }
};
