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
        if (Schema::hasColumn('partner_companies', 'assigned_credit_limit')) {
            return;
        }

        Schema::table('partner_companies', function (Blueprint $table): void {
            $table->decimal('assigned_credit_limit', 15, 2)
                ->nullable()
                ->after('notes')
                ->comment('Cupo de crédito asignado a la empresa aliada (USD)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('partner_companies', 'assigned_credit_limit')) {
            return;
        }

        Schema::table('partner_companies', function (Blueprint $table): void {
            $table->dropColumn('assigned_credit_limit');
        });
    }
};
