<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_rol', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rol_id')->constrained('rols')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rol_id', 'branch_id'], 'branch_rol_rol_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_rol');
    }
};
