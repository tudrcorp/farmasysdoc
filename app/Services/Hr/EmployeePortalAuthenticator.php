<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class EmployeePortalAuthenticator
{
    public function findActiveByIdentifier(string $identifier): ?Employee
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $byPhone = $this->findByPhone($identifier);
        if ($byPhone instanceof Employee) {
            return $byPhone;
        }

        return $this->findByNationalId($identifier);
    }

    public function attempt(?Employee $employee, ?string $password): Employee
    {
        if (! $employee instanceof Employee || ! $employee->is_active) {
            throw ValidationException::withMessages([
                'identifier' => 'No encontramos un empleado activo con esos datos.',
            ]);
        }

        if ($employee->hasPortalPassword()) {
            if (! filled($password) || ! Hash::check((string) $password, (string) $employee->portal_password)) {
                throw ValidationException::withMessages([
                    'password' => 'La clave no es correcta.',
                ]);
            }
        }

        return $employee;
    }

    public function setPassword(Employee $employee, string $password): void
    {
        $employee->forceFill([
            'portal_password' => $password,
        ])->save();
    }

    public function clearPassword(Employee $employee): void
    {
        $employee->forceFill([
            'portal_password' => null,
        ])->save();
    }

    public function ensureNotRateLimited(string $ip): void
    {
        $key = $this->rateLimitKey($ip);

        if (! RateLimiter::tooManyAttempts($key, 8)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'identifier' => 'Demasiados intentos. Espera '.$seconds.' segundos e inténtalo de nuevo.',
        ]);
    }

    public function hitRateLimit(string $ip): void
    {
        RateLimiter::hit($this->rateLimitKey($ip), 60);
    }

    public function clearRateLimit(string $ip): void
    {
        RateLimiter::clear($this->rateLimitKey($ip));
    }

    private function rateLimitKey(string $ip): string
    {
        return 'employee-portal-login:'.$ip;
    }

    private function findByNationalId(string $identifier): ?Employee
    {
        $digits = preg_replace('/\D/', '', $identifier) ?? '';

        if (strlen($digits) < 5) {
            return null;
        }

        return Employee::query()
            ->where('is_active', true)
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(national_id), ' ', ''), '.', ''), '-', ''), 'V', ''), 'E', '') = ?",
                [$digits],
            )
            ->first();
    }

    private function findByPhone(string $identifier): ?Employee
    {
        $normalized = WhatsAppLink::normalizePhoneDigits($identifier);

        if ($normalized === null) {
            return null;
        }

        $lastTen = substr($normalized, -10);

        if (strlen($lastTen) < 10) {
            return null;
        }

        return Employee::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->whereRaw(
                "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '.', ''), '(', ''), 10) = ?",
                [$lastTen],
            )
            ->first();
    }
}
