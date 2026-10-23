<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_chart_nodes', function (Blueprint $table) {
            $table->string('shape')->default('card')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('org_chart_nodes', function (Blueprint $table) {
            $table->dropColumn('shape');
        });
    }
};
