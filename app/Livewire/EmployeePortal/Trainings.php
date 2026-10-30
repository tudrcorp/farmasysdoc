<?php

namespace App\Livewire\EmployeePortal;

use App\Enums\TrainingSessionStatus;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Training\TrainingComplianceService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Capacitaciones')]
class Trainings extends Component
{
    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');

        $enrollments = $employee->trainingEnrollments()
            ->with('session.course')
            ->get()
            ->filter(fn (TrainingEnrollment $enrollment): bool => $enrollment->session->status !== TrainingSessionStatus::Cancelled)
            ->sortByDesc(fn (TrainingEnrollment $enrollment) => $enrollment->session->starts_at);

        [$history, $upcoming] = $enrollments->partition(
            fn (TrainingEnrollment $enrollment): bool => $enrollment->session->status === TrainingSessionStatus::Finished,
        );

        return view('livewire.employee-portal.trainings', [
            'employee' => $employee,
            'upcoming' => $upcoming->sortBy(fn (TrainingEnrollment $enrollment) => $enrollment->session->starts_at)->values(),
            'history' => $history->values(),
            'pending' => app(TrainingComplianceService::class)->pendingFor($employee),
        ]);
    }
}
