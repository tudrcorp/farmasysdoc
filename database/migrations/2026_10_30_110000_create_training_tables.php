<?php

use App\Enums\TrainingAttendance;
use App\Enums\TrainingSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->decimal('duration_hours', 6, 2);
            $table->boolean('is_mandatory')->default(false);
            $table->json('required_job_titles')->nullable();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->boolean('requires_evaluation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_course_id')->constrained('training_courses')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('modality', 20);
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->foreignId('facilitator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('facilitator_name', 180)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->decimal('cost_usd', 12, 2)->nullable();
            $table->decimal('cost_ves', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default(TrainingSessionStatus::Scheduled->value)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('attendance', 20)->default(TrainingAttendance::Pending->value);
            $table->boolean('passed')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['training_session_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_courses');
    }
};
