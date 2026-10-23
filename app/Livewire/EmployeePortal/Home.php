<?php

namespace App\Livewire\EmployeePortal;

use App\Enums\EmployeeRequestStatus;
use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Inicio')]
class Home extends Component
{
    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function greeting(): string
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return 'Buenos días';
        }

        if ($hour < 19) {
            return 'Buenas tardes';
        }

        return 'Buenas noches';
    }

    public function leave(): void
    {
        app(EmployeePortalAccess::class)->forget();

        $this->redirectRoute('employee-portal.login');
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');

        return view('livewire.employee-portal.home', [
            'employee' => $employee,
            'greeting' => $this->greeting(),
            'fileComplete' => $employee->hasCompleteEmployeeFile(),
            'hasPortalPassword' => $employee->hasPortalPassword(),
            'pendingRequestsCount' => $employee->employeeRequests()->where('status', EmployeeRequestStatus::Pending)->count(),
            'requestCards' => [
                ['href' => route('employee-portal.requests.certificate'), 'title' => 'Constancia de trabajo', 'subtitle' => 'Con sueldo integral o sueldo de ley. La descargas al aprobarse.', 'icon' => 'employment_certificate', 'tone' => 'cyan'],
                ['href' => route('employee-portal.requests.absence', 'vacaciones'), 'title' => 'Vacaciones', 'subtitle' => 'Mira tu período calculado y elige tus fechas.', 'icon' => 'vacation', 'tone' => 'amber'],
                ['href' => route('employee-portal.requests.absence', 'permiso'), 'title' => 'Permiso', 'subtitle' => 'Pide un permiso de ausencia con su motivo.', 'icon' => 'leave', 'tone' => 'violet'],
                ['href' => route('employee-portal.requests.absence', 'reposo'), 'title' => 'Reposo', 'subtitle' => 'Carga tu reposo médico con sus soportes.', 'icon' => 'medical_rest', 'tone' => 'rose'],
            ],
        ]);
    }
}
