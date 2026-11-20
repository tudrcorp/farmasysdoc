<?php

namespace App\Services\Partners;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de solo lectura de la API de medicamentos no cubiertos de Tu Dr. Group.
 *
 * El token viaja únicamente en el encabezado Authorization.
 */
class UncoveredMedicineClient
{
    /**
     * @return array<string, mixed>
     */
    public function profile(string $token): array
    {
        $response = $this->send($token, 'perfil');
        $payload = $response->json();

        return is_array($payload) ? $this->sanitize($payload) : [];
    }

    public function list(string $token, int $page, int $perPage): UncoveredMedicinePage
    {
        $perPage = min(100, max(1, $perPage));
        $page = max(1, $page);

        $response = $this->send($token, 'medicamentos-no-cubiertos', [
            'pagina' => $page,
            'por_pagina' => $perPage,
        ]);

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new UncoveredMedicineApiException('Tu Dr. Group devolvió una respuesta que no se puede leer.', $response->status());
        }

        $rows = $payload['data'] ?? [];
        if (! is_array($rows)) {
            throw new UncoveredMedicineApiException('Tu Dr. Group devolvió un listado inválido.', $response->status());
        }

        if (! array_is_list($rows)) {
            $rows = [$rows];
        }

        /** @var list<array<string, mixed>> $normalized */
        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $lastPage = max(1, (int) ($meta['ultima_pagina'] ?? 1));
        $limits = $this->rateLimits($response);

        return new UncoveredMedicinePage(
            rows: $normalized,
            currentPage: max(1, (int) ($meta['pagina_actual'] ?? $page)),
            lastPage: $lastPage,
            total: max(0, (int) ($meta['total'] ?? count($normalized))),
            rateLimitLimit: $limits['limit'],
            rateLimitRemaining: $limits['remaining'],
        );
    }

    /**
     * @param  array<string, int>  $query
     */
    private function send(string $token, string $path, array $query = []): Response
    {
        $token = $this->normalizeToken($token);
        if ($token === '') {
            throw new UncoveredMedicineApiException('Falta el token del aliado.', 401);
        }

        $attempts = 0;
        $maxAttempts = 3;

        while (true) {
            $attempts++;

            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout((int) config('partner_uncovered_medicines.timeout', 20))
                    ->connectTimeout(10)
                    ->get($this->endpoint($path), $query);
            } catch (ConnectionException) {
                if ($attempts >= $maxAttempts) {
                    throw new UncoveredMedicineApiException('No se pudo conectar con Tu Dr. Group. Intente más tarde.');
                }

                sleep($attempts);

                continue;
            }

            if ($response->status() === 503 && $attempts < $maxAttempts) {
                sleep($attempts * 2);

                continue;
            }

            if ($response->successful()) {
                return $response;
            }

            throw $this->exceptionFor($response);
        }
    }

    private function endpoint(string $path): string
    {
        $base = rtrim((string) config('partner_uncovered_medicines.base_url'), '/');
        if (! str_starts_with($base, 'https://')) {
            throw new UncoveredMedicineApiException('La URL de la API debe usar HTTPS.');
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);
        if (str_starts_with(strtolower($token), 'bearer ')) {
            return trim(substr($token, 7));
        }

        return $token;
    }

    private function exceptionFor(Response $response): UncoveredMedicineApiException
    {
        $error = $response->json('error');
        $error = is_string($error) ? $error : null;
        $status = $response->status();

        $message = match ($status) {
            401 => 'El token no es válido o falta. Pida uno nuevo a Tu Dr. Group.',
            403 => match ($error) {
                'https_requerido' => 'La API exige HTTPS.',
                'acceso_inactivo', 'acceso_vencido' => 'El acceso fue desactivado o venció. Contacte a Tu Dr. Group.',
                'sin_permiso' => 'Este aliado no tiene habilitada la consulta de medicamentos no cubiertos.',
                default => 'Tu Dr. Group rechazó el acceso.',
            },
            404 => 'No se encontró el recurso en Tu Dr. Group.',
            422 => 'Tu Dr. Group rechazó la consulta. Revise los filtros.',
            429 => 'Se alcanzó el límite de peticiones. Espere y vuelva a actualizar.',
            503 => 'El servicio de Tu Dr. Group no está disponible. Intente más tarde.',
            default => 'Tu Dr. Group respondió con un error ('.$status.').',
        };

        return new UncoveredMedicineApiException(
            $message,
            $status,
            $status === 429 ? $this->retryAfterSeconds($response) : null,
        );
    }

    private function retryAfterSeconds(Response $response): int
    {
        $header = $response->header('Retry-After');
        if (! is_string($header) || trim($header) === '') {
            return 60;
        }

        if (is_numeric($header)) {
            return max(1, (int) $header);
        }

        try {
            $until = CarbonImmutable::parse($header)->getTimestamp();
        } catch (\Throwable) {
            return 60;
        }

        return max(1, $until - now()->getTimestamp());
    }

    /**
     * @return array{limit: ?int, remaining: ?int}
     */
    private function rateLimits(Response $response): array
    {
        $limit = $response->header('X-RateLimit-Limit');
        $remaining = $response->header('X-RateLimit-Remaining');

        return [
            'limit' => is_numeric($limit) ? (int) $limit : null,
            'remaining' => is_numeric($remaining) ? (int) $remaining : null,
        ];
    }

    /**
     * Quita cualquier campo que parezca un secreto antes de guardar el perfil.
     *
     * @return array<string, mixed>
     */
    private function sanitize(mixed $value, int $depth = 0): array
    {
        if (! is_array($value) || $depth > 4) {
            return [];
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $keyName = strtolower((string) $key);
            if (str_contains($keyName, 'token') || str_contains($keyName, 'secret') || str_contains($keyName, 'password') || str_contains($keyName, 'authorization')) {
                continue;
            }

            if (is_array($item)) {
                $clean[$key] = $this->sanitize($item, $depth + 1);

                continue;
            }

            if (is_scalar($item) || $item === null) {
                $clean[$key] = $item;
            }
        }

        return $clean;
    }
}
