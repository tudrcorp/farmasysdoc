<?php

namespace App\Livewire\EmployeePortal;

use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmployeePortalAuthenticator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Entrar')]
class Login extends Component
{
    public string $identifier = '';

    public string $password = '';

    #[Locked]
    public bool $needsPassword = false;

    #[Locked]
    public ?int $pendingEmployeeId = null;

    public function continue(EmployeePortalAuthenticator $authenticator): void
    {
        $this->resetErrorBag();
        $authenticator->ensureNotRateLimited((string) request()->ip());

        $this->validate([
            'identifier' => ['required', 'string', 'max:40'],
        ], [
            'identifier.required' => 'Escribe tu cédula o tu teléfono.',
        ]);

        $employee = $authenticator->findActiveByIdentifier($this->identifier);

        if (! $employee instanceof Employee) {
            $authenticator->hitRateLimit((string) request()->ip());

            throw ValidationException::withMessages([
                'identifier' => 'No encontramos un empleado activo con esos datos.',
            ]);
        }

        if ($employee->hasPortalPassword()) {
            $this->pendingEmployeeId = $employee->getKey();
            $this->needsPassword = true;
            $this->password = '';

            return;
        }

        $this->openSession($employee, $authenticator);
    }

    public function authenticate(EmployeePortalAuthenticator $authenticator): void
    {
        $this->resetErrorBag();
        $authenticator->ensureNotRateLimited((string) request()->ip());

        $this->validate([
            'password' => ['required', 'string', 'max:72'],
        ], [
            'password.required' => 'Escribe tu clave.',
        ]);

        $employee = Employee::query()->find($this->pendingEmployeeId);

        try {
            $employee = $authenticator->attempt($employee, $this->password);
        } catch (ValidationException $exception) {
            $authenticator->hitRateLimit((string) request()->ip());

            throw $exception;
        }

        $this->openSession($employee, $authenticator);
    }

    public function backToIdentifier(): void
    {
        $this->needsPassword = false;
        $this->pendingEmployeeId = null;
        $this->password = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.employee-portal.login');
    }

    private function openSession(Employee $employee, EmployeePortalAuthenticator $authenticator): void
    {
        $authenticator->clearRateLimit((string) request()->ip());
        app(EmployeePortalAccess::class)->start($employee);
        $this->redirectRoute('employee-portal.home');
    }
}
