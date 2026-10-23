<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->string('bank_account_type', 20)->nullable()->change();
            $table->string('bank_account_number', 30)->nullable()->change();
            $table->string('bank_account_holder')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->string('bank_account_type', 20)->nullable(false)->change();
            $table->string('bank_account_number', 30)->nullable(false)->change();
            $table->string('bank_account_holder')->nullable(false)->change();
        });
    }
};
