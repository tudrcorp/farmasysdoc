<?php

namespace App\Services\Partners;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Consulta de solo lectura de los pedidos que Farmadoc envió y Tu Dr. Group recibió en inventario.
 */
class FarmadocOrderReceiptClient
{
    /**
     * @return array{rows: list<array<string, mixed>>, last_page: int}
     */
    public function listRegistered(string $token, ?CarbonInterface $updatedSince, int $page): array
    {
        $query = [
            'estatus' => 'registrado-inventario',
            'por_pagina' => 100,
            'pagina' => max(1, $page),
        ];

        if ($updatedSince instanceof CarbonInterface) {
            $query['actualizado_desde'] = $updatedSince->toIso8601String();
        }

        $payload = $this->json($token, 'pedidos-farmadoc', $query);
        $rows = $payload['data'] ?? [];

        if (! is_array($rows)) {
            throw new FarmadocOrderReceiptException('Tu Dr. Group devolvió un listado de pedidos que no se puede leer.');
        }

        if (! array_is_list($rows)) {
            $rows = [$rows];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return [
            'rows' => $normalized,
            'last_page' => max(1, (int) ($meta['ultima_pagina'] ?? 1)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $token, string $orderNumber): ?array
    {
        $payload = $this->json($token, 'pedidos-farmadoc/'.rawurlencode($orderNumber), allowNotFound: true);

        if ($payload === null) {
            return null;
        }

        $data = $payload['data'] ?? $payload;

        return is_array($data) ? $data : null;
    }

    public function downloadNote(string $token, string $orderNumber): ?string
    {
        $response = $this->send(
            $token,
            'pedidos-farmadoc/'.rawurlencode($orderNumber).'/nota-de-recibido',
            accept: 'application/pdf',
        );

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw $this->exceptionFor($response);
        }

        $body = $response->body();

        return str_starts_with($body, '%PDF') ? $body : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function json(string $token, string $path, array $query = [], bool $allowNotFound = false): ?array
    {
        $response = $this->send($token, $path, $query);

        if ($allowNotFound && $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw $this->exceptionFor($response);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new FarmadocOrderReceiptException('Tu Dr. Group devolvió una respuesta que no se puede leer.', $response->status());
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $token, string $path, array $query = [], string $accept = 'application/json'): Response
    {
        $token = trim($token);
        if (str_starts_with(strtolower($token), 'bearer ')) {
            $token = trim(substr($token, 7));
        }

        if ($token === '') {
            throw new FarmadocOrderReceiptException('Falta el token para consultar los pedidos en Tu Dr. Group.', 401);
        }

        $base = rtrim((string) config('partner_uncovered_medicines.base_url'), '/');
        if (! str_starts_with($base, 'https://')) {
            throw new FarmadocOrderReceiptException('La URL de la API debe usar HTTPS.');
        }

        try {
            return Http::withToken($token)
                ->accept($accept)
                ->timeout((int) config('partner_uncovered_medicines.timeout', 20))
                ->connectTimeout(8)
                ->get($base.'/'.ltrim($path, '/'), $query);
        } catch (ConnectionException) {
            throw new FarmadocOrderReceiptException('No se pudo conectar con Tu Dr. Group. Intente más tarde.');
        }
    }

    private function exceptionFor(Response $response): FarmadocOrderReceiptException
    {
        $error = $response->json('error');
        $error = is_string($error) ? $error : null;
        $status = $response->status();

        $message = match ($status) {
            401 => 'El token no es válido o falta. Pida uno nuevo a Tu Dr. Group.',
            403 => $error === 'sin_permiso'
                ? 'El token no tiene permiso para consultar los pedidos recibidos en inventario.'
                : 'Tu Dr. Group rechazó el acceso a los pedidos.',
            422 => 'Tu Dr. Group rechazó la consulta de pedidos.',
            429 => 'Se alcanzó el límite de peticiones a Tu Dr. Group.',
            503 => 'El servicio de Tu Dr. Group no está disponible.',
            default => 'Tu Dr. Group respondió con un error ('.$status.').',
        };

        return new FarmadocOrderReceiptException($message, $status, $error);
    }
}
