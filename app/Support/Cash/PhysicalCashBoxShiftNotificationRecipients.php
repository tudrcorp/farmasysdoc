<?php

namespace App\Support\Cash;

use App\Models\User;

final class PhysicalCashBoxShiftNotificationRecipients
{
    /**
     * @return list<string>
     */
    public function phonesFor(User $cashier): array
    {
        $cashierBranchId = filled($cashier->branch_id) ? (int) $cashier->branch_id : null;

        $phones = User::query()
            ->with('managedBranches:id')
            ->get(['id', 'roles', 'branch_id', 'whatsapp_phone', 'delivery_mobile_phone'])
            ->filter(fn (User $user): bool => $this->shouldNotifyUser($user, $cashierBranchId))
            ->map(fn (User $user): ?string => $this->normalizePhone(
                filled($user->whatsapp_phone) ? $user->whatsapp_phone : $user->delivery_mobile_phone
            ))
            ->filter()
            ->values()
            ->all();

        $fallbackPhones = collect(
            explode(',', (string) config('services.ultramsg.admin_fallback_phones', ''))
        )
            ->map(fn (string $phone): ?string => $this->normalizePhone($phone))
            ->filter()
            ->values()
            ->all();

        return array_values(array_unique([...$phones, ...$fallbackPhones]));
    }

    /**
     * @return list<string>
     */
    public function emailsFor(User $cashier): array
    {
        $cashierBranchId = filled($cashier->branch_id) ? (int) $cashier->branch_id : null;

        return User::query()
            ->with('managedBranches:id')
            ->get(['id', 'roles', 'branch_id', 'email'])
            ->filter(fn (User $user): bool => $this->shouldNotifyUser($user, $cashierBranchId))
            ->map(fn (User $user): string => strtolower(trim((string) $user->email)))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }

    private function shouldNotifyUser(User $user, ?int $cashierBranchId): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if ($cashierBranchId === null) {
            return false;
        }

        $allowed = $user->restrictedBranchIdsForQueries();
        if ($allowed !== []) {
            return in_array($cashierBranchId, $allowed, true);
        }

        return false;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $raw = trim((string) $phone);
        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        $raw = preg_replace('/[^0-9+]/', '', $raw) ?? '';

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '00')) {
            $raw = '+'.substr($raw, 2);
        }

        $digitsOnly = preg_replace('/\D/', '', $raw) ?? '';

        if (! str_starts_with($raw, '+')) {
            if (str_starts_with($digitsOnly, '0') && strlen($digitsOnly) === 11) {
                $raw = '+58'.substr($digitsOnly, 1);
            } elseif (str_starts_with($digitsOnly, '58') && strlen($digitsOnly) >= 10) {
                $raw = '+'.$digitsOnly;
            } elseif (str_starts_with($digitsOnly, '4') && strlen($digitsOnly) === 10) {
                $raw = '+58'.$digitsOnly;
            } else {
                $raw = '+'.$digitsOnly;
            }
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $raw;
    }
}
