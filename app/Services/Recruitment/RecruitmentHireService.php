<?php

namespace App\Services\Recruitment;

use App\Enums\RecruitmentRequestStatus;
use App\Enums\RecruitmentStage;
use App\Models\Employee;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use App\Models\User;
use App\Services\Training\TrainingEnrollmentService;
use App\Support\Recruitment\RecruitmentNationalId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Convierte al candidato en empleado. Si la cédula ya existe (recontratación)
 * actualiza esa ficha, porque `employees.national_id` es único.
 */
class RecruitmentHireService
{
    public function __construct(
        private RecruitmentSelectionService $selection,
        private TrainingEnrollmentService $training,
    ) {}

    /**
     * @param  array{branch_id: int|string, job_title: string, hired_at: mixed, monthly_salary_usd: mixed, legal_salary_ves: mixed}  $data
     */
    public function hire(RecruitmentApplication $application, array $data, User $user): Employee
    {
        return DB::transaction(function () use ($application, $data, $user): Employee {
            $application = RecruitmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            $request = RecruitmentRequest::query()->lockForUpdate()->findOrFail($application->recruitment_request_id);
            $candidate = $application->candidate;

            $this->ensureCanHire($application, $request, $candidate);

            $employee = RecruitmentNationalId::findEmployee($candidate->national_id) ?? new Employee;
            $employee->fill([
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'phone' => $candidate->phone,
                'email' => $candidate->email,
                'address' => $candidate->address,
                'branch_id' => $data['branch_id'],
                'job_title' => $data['job_title'],
                'hired_at' => $data['hired_at'],
                'monthly_salary_usd' => $data['monthly_salary_usd'],
                'legal_salary_ves' => $data['legal_salary_ves'],
                'is_active' => true,
            ]);

            if (! $employee->exists) {
                $employee->national_id = $candidate->national_id;
            }

            $employee->photo_path = $this->copyPhoto($candidate) ?? $employee->photo_path;
            $employee->cv_path = $this->copyCv($candidate) ?? $employee->cv_path;
            $employee->save();

            $candidate->update(['employee_id' => $employee->id]);

            $this->selection->transition($application, RecruitmentStage::Hired, $user, 'Contratado como empleado.');

            $this->closeOtherProcesses($candidate, $application, $user);
            $this->closeRequestWhenFilled($request, $user);
            $this->training->enrollInInduction($employee);

            return $employee;
        });
    }

    private function ensureCanHire(RecruitmentApplication $application, RecruitmentRequest $request, RecruitmentCandidate $candidate): void
    {
        if ($application->stage !== RecruitmentStage::Offer) {
            throw new RuntimeException('Solo se puede contratar a un candidato en etapa de oferta.');
        }

        if (! $request->isApproved()) {
            throw new RuntimeException('La solicitud de personal ya no está activa.');
        }

        if (! $candidate->isReviewed() || blank($candidate->national_id)) {
            throw new RuntimeException('Hay que revisar los datos del candidato antes de contratarlo.');
        }
    }

    private function copyPhoto(RecruitmentCandidate $candidate): ?string
    {
        $disk = Storage::disk(RecruitmentCandidate::PHOTO_DISK);
        $source = (string) $candidate->photo_path;

        if ($source === '' || ! $disk->exists($source)) {
            return null;
        }

        $target = 'employees/'.basename($source);

        return $disk->copy($source, $target) ? $target : null;
    }

    private function copyCv(RecruitmentCandidate $candidate): ?string
    {
        if (! $candidate->hasCv()) {
            return null;
        }

        $disk = Storage::disk(RecruitmentCandidate::CV_DISK);
        $target = 'employees/cvs/'.basename((string) $candidate->cv_path);

        return $disk->copy((string) $candidate->cv_path, $target) ? $target : null;
    }

    private function closeOtherProcesses(RecruitmentCandidate $candidate, RecruitmentApplication $hiredApplication, User $user): void
    {
        $candidate->applications()
            ->whereKeyNot($hiredApplication->id)
            ->whereIn('stage', RecruitmentStage::open())
            ->each(fn (RecruitmentApplication $application) => $this->selection->discard($application, 'Contratado en otra vacante.', $user));
    }

    private function closeRequestWhenFilled(RecruitmentRequest $request, User $user): void
    {
        if ($request->hiredCount() < $request->vacancies) {
            return;
        }

        $request->update(['status' => RecruitmentRequestStatus::Filled]);

        $request->applications()
            ->whereIn('stage', RecruitmentStage::open())
            ->each(fn (RecruitmentApplication $application) => $this->selection->discard($application, 'Vacantes cubiertas.', $user));
    }
}
