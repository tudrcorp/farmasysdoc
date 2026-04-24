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

        $path = $request->path();
        if (self::isLivewirePath($path)) {
            return true;
        }

        $route = $request->route();
        if ($route !== null && $route->named('*livewire.*')) {
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

    private static function isLivewirePath(string $path): bool
    {
        $normalized = ltrim($path, '/');
        if ($normalized === '') {
            return false;
        }

        $livewirePathPrefix = ltrim(EndpointResolver::prefix(), '/');
        if ($livewirePathPrefix !== '' && str_contains($normalized, $livewirePathPrefix.'/')) {
            return true;
        }

        return preg_match('/(^|\/)livewire(?:-[^\/]+)?\/(?:update|upload-file|preview-file)(?:\/|$)/', $normalized) === 1;
    }
}
