<?php

namespace App\Livewire\EmployeePortal;

use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmployeePortalAuthenticator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee-portal')]
#[Title('Tu clave')]
class Account extends Component
{
    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $saved = false;

    public bool $justCreated = false;

    public function employee(): Employee
    {
        $employee = app(EmployeePortalAccess::class)->employee();
        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    public function save(EmployeePortalAuthenticator $authenticator): void
    {
        $this->saved = false;
        $this->justCreated = false;
        $employee = $this->employee();
        $hasPassword = $employee->hasPortalPassword();

        $rules = [
            'password' => ['required', 'string', 'min:4', 'max:72'],
            'passwordConfirmation' => ['required', 'same:password'],
        ];

        if ($hasPassword) {
            $rules['currentPassword'] = ['required', 'string'];
        }

        $this->validate($rules, [
            'currentPassword.required' => 'Escribe tu clave actual.',
            'password.required' => 'Escribe la nueva clave.',
            'password.min' => 'Usa al menos 4 caracteres.',
            'passwordConfirmation.required' => 'Repite la clave.',
            'passwordConfirmation.same' => 'Las claves no coinciden.',
        ]);

        if ($hasPassword && ! Hash::check($this->currentPassword, (string) $employee->portal_password)) {
            $this->addError('currentPassword', 'La clave actual no es correcta.');

            return;
        }

        $authenticator->setPassword($employee, $this->password);
        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        $this->saved = true;
        $this->justCreated = ! $hasPassword;
    }

    public function remove(EmployeePortalAuthenticator $authenticator): void
    {
        $this->saved = false;
        $this->justCreated = false;
        $employee = $this->employee();

        if (! $employee->hasPortalPassword()) {
            return;
        }

        $this->validate([
            'currentPassword' => ['required', 'string'],
        ], [
            'currentPassword.required' => 'Escribe tu clave actual para quitarla.',
        ]);

        if (! Hash::check($this->currentPassword, (string) $employee->portal_password)) {
            $this->addError('currentPassword', 'La clave actual no es correcta.');

            return;
        }

        $authenticator->clearPassword($employee);
        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        $this->saved = true;
    }

    public function render(): View
    {
        $employee = $this->employee();
        $employee->loadMissing('branch');

        return view('livewire.employee-portal.account', [
            'employee' => $employee,
            'hasPassword' => $employee->hasPortalPassword(),
        ]);
    }
}
