<?php

namespace App\Livewire\EmployeePortal;

use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use App\Services\Hr\EmployeePortalAuthenticator;
use App\Services\Hr\EmployeePortalPasswordReset;
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

    #[Locked]
    public ?string $recoveryStep = null;

    #[Locked]
    public bool $otpVerified = false;

    public string $otpChannel = '';

    public string $otpCode = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

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
        $this->resetRecoveryState();
        $this->resetErrorBag();
    }

    public function startPasswordRecovery(): void
    {
        if (! $this->needsPassword || $this->pendingEmployeeId === null) {
            return;
        }

        $this->resetErrorBag();
        $this->otpVerified = false;
        $this->otpChannel = '';
        $this->otpCode = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->recoveryStep = 'channel';
    }

    public function sendRecoveryOtpTo(string $channel, EmployeePortalPasswordReset $reset): void
    {
        $this->otpChannel = $channel;
        $this->sendRecoveryOtp($reset);
    }

    public function sendRecoveryOtp(EmployeePortalPasswordReset $reset): void
    {
        $employee = $this->recoveryEmployee();

        $this->validate([
            'otpChannel' => ['required', 'in:phone,email'],
        ], [
            'otpChannel.required' => 'Elige dónde enviar el código.',
            'otpChannel.in' => 'Elige el teléfono o el correo.',
        ]);

        $reset->issue($employee, $this->otpChannel, (string) request()->ip());
        $this->otpCode = '';
        $this->otpVerified = false;
        $this->recoveryStep = 'otp';
        $this->resetErrorBag();
    }

    public function verifyRecoveryOtp(EmployeePortalPasswordReset $reset): void
    {
        $employee = $this->recoveryEmployee();
        $this->otpCode = preg_replace('/\D/', '', $this->otpCode) ?? '';

        $this->validate([
            'otpCode' => ['required', 'string', 'size:6'],
        ], [
            'otpCode.required' => 'Escribe el código de 6 dígitos.',
            'otpCode.size' => 'El código debe tener 6 dígitos.',
        ]);

        $reset->verify($employee, $this->otpCode, (string) request()->ip());
        $this->otpVerified = true;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->recoveryStep = 'password';
        $this->resetErrorBag();
    }

    public function saveRecoveredPassword(EmployeePortalPasswordReset $reset): void
    {
        $employee = $this->recoveryEmployee();

        if (! $this->otpVerified) {
            $this->recoveryStep = 'otp';

            return;
        }

        $this->validate([
            'newPassword' => ['required', 'string', 'min:4', 'max:72'],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ], [
            'newPassword.required' => 'Escribe tu nueva clave.',
            'newPassword.min' => 'Usa al menos 4 caracteres.',
            'newPasswordConfirmation.required' => 'Repite la clave.',
            'newPasswordConfirmation.same' => 'Las claves no coinciden.',
        ]);

        $reset->resetPassword($employee, $this->newPassword);
        $this->reset('newPassword', 'newPasswordConfirmation', 'otpCode', 'password');
        $this->recoveryStep = 'done';
        $this->resetErrorBag();
    }

    public function backToPasswordStep(): void
    {
        $this->resetRecoveryState();
        $this->resetErrorBag();
    }

    public function backToRecoveryChannel(): void
    {
        $this->recoveryStep = 'channel';
        $this->otpCode = '';
        $this->otpVerified = false;
        $this->resetErrorBag();
    }

    public function backToRecoveryOtp(): void
    {
        $this->recoveryStep = 'otp';
        $this->otpVerified = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $employee = $this->pendingEmployeeId
            ? Employee::query()->find($this->pendingEmployeeId)
            : null;

        return view('livewire.employee-portal.login', [
            'recoveryEmployee' => $employee,
            'canSendPhone' => $employee instanceof Employee && app(EmployeePortalPasswordReset::class)->canSendToPhone($employee),
            'canSendEmail' => $employee instanceof Employee && app(EmployeePortalPasswordReset::class)->canSendToEmail($employee),
        ]);
    }

    private function recoveryEmployee(): Employee
    {
        $employee = Employee::query()->find($this->pendingEmployeeId);

        if (! $employee instanceof Employee || ! $employee->is_active || ! $employee->hasPortalPassword()) {
            $this->backToIdentifier();

            throw ValidationException::withMessages([
                'identifier' => 'Vuelve a identificarte para restablecer la clave.',
            ]);
        }

        return $employee;
    }

    private function resetRecoveryState(): void
    {
        $this->recoveryStep = null;
        $this->otpVerified = false;
        $this->otpChannel = '';
        $this->otpCode = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
    }

    private function openSession(Employee $employee, EmployeePortalAuthenticator $authenticator): void
    {
        $authenticator->clearRateLimit((string) request()->ip());
        app(EmployeePortalAccess::class)->start($employee);
        $this->redirectRoute('employee-portal.home');
    }
}
