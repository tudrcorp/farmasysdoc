<?php

namespace App\Livewire\EmployeePortal;

use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Datos personales')]
class Profile extends Component
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

        return view('livewire.employee-portal.profile', [
            'employee' => $employee,
            'fields' => $this->personalFields($employee),
        ]);
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function personalFields(Employee $employee): array
    {
        return [
            ['label' => 'Nombre', 'value' => $this->display($employee->first_name)],
            ['label' => 'Apellido', 'value' => $this->display($employee->last_name)],
            ['label' => 'Cédula de identidad', 'value' => $employee->formattedNationalId() ?? $this->display($employee->national_id)],
            ['label' => 'Sucursal', 'value' => $this->display($employee->branch?->name)],
            ['label' => 'Teléfono', 'value' => $this->display($employee->phone)],
            ['label' => 'Correo', 'value' => $this->display($employee->email)],
            ['label' => 'Dirección', 'value' => $this->display($employee->address)],
            ['label' => 'Estado laboral', 'value' => $employee->is_active ? 'Activo' : 'Inactivo'],
        ];
    }

    private function display(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'Sin registrar';
    }
}
