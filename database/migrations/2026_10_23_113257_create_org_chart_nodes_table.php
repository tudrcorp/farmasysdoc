<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_chart_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_chart_id')->constrained('org_charts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('org_chart_nodes')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title');
            $table->string('area')->nullable();
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->timestamps();

            $table->index(['org_chart_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_chart_nodes');
    }
};
