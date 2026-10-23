<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->string('holder_phone', 40)->nullable()->after('bank_account_holder');
            $table->string('holder_national_id', 50)->nullable()->after('holder_phone');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->dropColumn(['holder_phone', 'holder_national_id']);
        });
    }
};
