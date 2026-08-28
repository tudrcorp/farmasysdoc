<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'address')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->text('address')
                    ->nullable()
                    ->comment('Dirección fiscal de la sucursal, impresa en facturas fiscales')
                    ->change();
            });
        }

        Schema::create('fiscal_company_settings', function (Blueprint $table): void {
            $table->id();
            $table->text('address')
                ->nullable()
                ->comment('Dirección fiscal de la empresa principal (domicilio SENIAT)');
            $table->timestamps();
        });

        $now = now();
        $defaultAddress = trim((string) config('fiscal.retention_agent.address', ''));

        DB::table('fiscal_company_settings')->insert([
            'id' => 1,
            'address' => $defaultAddress !== '' ? $defaultAddress : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_company_settings');

        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'address')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->string('address')
                    ->nullable()
                    ->comment('Dirección física')
                    ->change();
            });
        }
    }
};
