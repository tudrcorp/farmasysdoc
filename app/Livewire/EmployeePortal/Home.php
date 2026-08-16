<?php

namespace App\Livewire\EmployeePortal;

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
    public bool $showComingSoon = false;

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

    public function openComingSoon(): void
    {
        $this->showComingSoon = true;
    }

    public function closeComingSoon(): void
    {
        $this->showComingSoon = false;
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
        ]);
    }
}
