<?php

declare(strict_types=1);

namespace App\Support\Livewire;

use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

final class LivewireRequestPayload
{
    /**
     * Evita que TrimStrings / ConvertEmptyStringsToNull alteren el JSON del snapshot de Livewire
     * (checksum / CorruptComponentPayloadException).
     */
    public static function shouldSkipNormalization(Request $request): bool
    {
        if ($request->headers->has('X-Livewire')) {
            return true;
        }

        $livewirePathPrefix = ltrim(EndpointResolver::prefix(), '/');
        $path = $request->path();
        if ($path !== '' && str_contains($path, $livewirePathPrefix.'/')) {
            return true;
        }

        if ($request->isMethod('POST') && $request->isJson()) {
            try {
                $payload = $request->json()->all();
            } catch (\Throwable) {
                return false;
            }

            if (isset($payload['components']) && is_array($payload['components'])) {
                return true;
            }
        }

        return false;
    }
}
