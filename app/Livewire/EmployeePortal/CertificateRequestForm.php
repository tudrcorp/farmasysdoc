<?php

namespace App\Livewire\EmployeePortal;

use App\Enums\EmployeeRequestStatus;
use App\Enums\EmployeeRequestType;
use App\Enums\EmploymentCertificateSalaryBasis;
use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmployeeRequestSubmitter;
use App\Services\Hr\EmploymentCertificatePdfFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Constancia de trabajo')]
class CertificateRequestForm extends Component
{
    public string $salaryBasis = 'integral';

    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function submit(): void
    {
        $this->validate([
            'salaryBasis' => ['required', Rule::enum(EmploymentCertificateSalaryBasis::class)],
        ], [
            'salaryBasis.required' => 'Elige el tipo de sueldo para la constancia.',
        ]);

        app(EmployeeRequestSubmitter::class)->submitCertificate(
            $this->employee(),
            EmploymentCertificateSalaryBasis::from($this->salaryBasis),
        );

        session()->flash('ep_request_flash', 'Enviamos tu solicitud de constancia. Te avisamos por correo y WhatsApp cuando esté aprobada.');

        $this->redirectRoute('employee-portal.requests', navigate: true);
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');
        $pdfFactory = app(EmploymentCertificatePdfFactory::class);

        return view('livewire.employee-portal.certificate-request-form', [
            'employee' => $employee,
            'hasEmploymentData' => $employee->hasEmploymentData(),
            'hasPending' => $employee->employeeRequests()
                ->where('type', EmployeeRequestType::EmploymentCertificate)
                ->where('status', EmployeeRequestStatus::Pending)
                ->exists(),
            'options' => collect(EmploymentCertificateSalaryBasis::cases())
                ->map(fn (EmploymentCertificateSalaryBasis $basis): array => [
                    'value' => $basis->value,
                    'label' => $basis->label(),
                    'description' => $basis->description(),
                    'amount' => $pdfFactory->salaryLabel($employee, $basis),
                    'available' => $basis !== EmploymentCertificateSalaryBasis::Legal || $employee->hasLegalSalary(),
                ])
                ->all(),
        ]);
    }
}
