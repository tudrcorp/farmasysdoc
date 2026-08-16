<?php

namespace App\Services\Hr;

use App\Models\Employee;

final class EmployeePortalAccess
{
    public const SESSION_KEY = 'employee_portal';

    public const SESSION_MINUTES = 10080;

    public function start(Employee $employee, int $ttlMinutes = self::SESSION_MINUTES): void
    {
        session([
            self::SESSION_KEY => [
                'employee_id' => $employee->getKey(),
                'expires_at' => now()->addMinutes($ttlMinutes)->getTimestamp(),
            ],
        ]);
    }

    public function employee(): ?Employee
    {
        $payload = session(self::SESSION_KEY);

        if (! is_array($payload)) {
            return null;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        $employeeId = $payload['employee_id'] ?? null;

        if ($expiresAt < now()->getTimestamp() || blank($employeeId)) {
            $this->forget();

            return null;
        }

        $employee = Employee::query()->find($employeeId);

        if (! $employee instanceof Employee || ! $employee->is_active) {
            $this->forget();

            return null;
        }

        return $employee;
    }

    public function touch(int $ttlMinutes = self::SESSION_MINUTES): void
    {
        $payload = session(self::SESSION_KEY);

        if (! is_array($payload) || blank($payload['employee_id'] ?? null)) {
            return;
        }

        $payload['expires_at'] = now()->addMinutes($ttlMinutes)->getTimestamp();
        session([self::SESSION_KEY => $payload]);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
