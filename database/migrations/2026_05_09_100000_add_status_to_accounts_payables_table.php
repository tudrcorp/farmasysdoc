<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->string('status', 32)
                ->default('por_pagar')
                ->comment('Estado: por pagar, etc.')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
