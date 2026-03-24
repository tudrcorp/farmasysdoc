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
        Schema::create('presentation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre de la forma farmacéutica');
            $table->string('slug')->unique()->comment('Slug de la forma farmacéutica');
            $table->string('description')->nullable()->comment('Descripción de la forma farmacéutica');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentation_types');
    }
};
