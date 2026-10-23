<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->foreignId('replenished_from_fund_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('petty_cash_funds')
                ->nullOnDelete();
            $table->foreignId('superseded_by_fund_id')
                ->nullable()
                ->after('replenished_from_fund_id')
                ->constrained('petty_cash_funds')
                ->nullOnDelete();
            $table->decimal('carried_over_usd', 14, 2)->default(0)->after('amount_ves');
            $table->decimal('carried_over_ves', 14, 2)->default(0)->after('carried_over_usd');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replenished_from_fund_id');
            $table->dropConstrainedForeignId('superseded_by_fund_id');
            $table->dropColumn(['carried_over_usd', 'carried_over_ves']);
        });
    }
};
