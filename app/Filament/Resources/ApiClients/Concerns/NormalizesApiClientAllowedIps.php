<?php

namespace App\Filament\Resources\ApiClients\Concerns;

use Illuminate\Validation\ValidationException;

trait NormalizesApiClientAllowedIps
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeAllowedIpsInFormData(array $data): array
    {
        $raw = $data['allowed_ips'] ?? null;

        if (! is_array($raw)) {
            $data['allowed_ips'] = null;

            return $data;
        }

        $ips = array_values(array_filter($raw, fn (mixed $v): bool => is_string($v) && $v !== ''));

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw ValidationException::withMessages([
                    'data.allowed_ips' => "La dirección IP «{$ip}» no es válida.",
                ]);
            }
        }

        $data['allowed_ips'] = $ips === [] ? null : $ips;

        return $data;
    }
}
