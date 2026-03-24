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
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('code')->nullable()->change();
        });

        foreach (DB::table('branches')->orderBy('id')->get() as $row) {
            DB::table('branches')->where('id', $row->id)->update([
                'code' => 'SUC-000'.$row->id,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('code')->nullable(false)->change();
        });
    }
};
