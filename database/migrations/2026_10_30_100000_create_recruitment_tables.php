<?php

use App\Enums\RecruitmentRequestPriority;
use App\Enums\RecruitmentRequestStatus;
use App\Enums\RecruitmentStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('job_title', 160);
            $table->unsignedSmallInteger('vacancies')->default(1);
            $table->string('reason', 20);
            $table->string('priority', 20)->default(RecruitmentRequestPriority::Normal->value);
            $table->text('requirements')->nullable();
            $table->decimal('reference_salary_usd', 12, 2)->nullable();
            $table->date('needed_by')->nullable();
            $table->string('status', 20)->default(RecruitmentRequestStatus::Pending->value)->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('national_id', 50)->nullable()->unique();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('desired_position', 160)->nullable();
            $table->unsignedTinyInteger('years_of_experience')->nullable();
            $table->text('education')->nullable();
            $table->text('work_experience')->nullable();
            $table->foreignId('preferred_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('source', 20)->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('cv_extraction_status', 20)->default('skipped');
            $table->text('cv_extraction_error')->nullable();
            $table->json('cv_extraction_flags')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recruitment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_request_id')->constrained('recruitment_requests')->cascadeOnDelete();
            $table->foreignId('recruitment_candidate_id')->constrained('recruitment_candidates')->cascadeOnDelete();
            $table->string('stage', 20)->default(RecruitmentStage::Applied->value)->index();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->dateTime('interview_at')->nullable();
            $table->text('discard_reason')->nullable();
            $table->timestamps();

            $table->unique(['recruitment_request_id', 'recruitment_candidate_id'], 'recruitment_applications_request_candidate_unique');
        });

        Schema::create('recruitment_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_application_id')->constrained('recruitment_applications', indexName: 'recruitment_events_application_id_foreign')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('cv_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('cv_path');
        });

        Schema::dropIfExists('recruitment_application_events');
        Schema::dropIfExists('recruitment_applications');
        Schema::dropIfExists('recruitment_candidates');
        Schema::dropIfExists('recruitment_requests');
    }
};
