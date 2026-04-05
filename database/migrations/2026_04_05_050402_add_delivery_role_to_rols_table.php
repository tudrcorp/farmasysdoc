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
        if (! Schema::hasTable('rols')) {
            Schema::create('rols', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();

        if (! DB::table('rols')->where('name', 'DELIVERY')->exists()) {
            DB::table('rols')->insert([
                'name' => 'DELIVERY',
                'description' => 'Usuario de logística y entregas',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rols')) {
            DB::table('rols')->where('name', 'DELIVERY')->delete();
        }
    }
};
