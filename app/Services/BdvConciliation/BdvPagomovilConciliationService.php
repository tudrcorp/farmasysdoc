<?php

namespace App\Services\BdvConciliation;

use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Models\Branch;
use App\Models\ConciliationBdv;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Conciliación operativa de Pagomóvil BDV (getMovement/v2) fuera de la caja.
 */
final class BdvPagomovilConciliationService
{
    public function __construct(
        private readonly BdvConciliationClient $client,
    ) {}

    public function resolveEnvironment(): string
    {
        $raw = config('bdv_conciliation.pos_conciliation_environment');
        if (is_string($raw) && $raw !== '') {
            $normalized = strtolower(trim($raw));
            if (in_array($normalized, ['qa', 'production'], true)) {
                return $normalized;
            }
        }

        return app()->isProduction() ? 'production' : 'qa';
    }

    public function resolveCommercePhone(?int $branchId): string
    {
        if ($branchId !== null && $branchId > 0) {
            $phone = trim((string) Branch::query()->whereKey($branchId)->value('pm_conciliation_phone'));
            if ($phone !== '') {
                return $phone;
            }
        }

        return trim((string) config('bdv_conciliation.commerce_mobile_phone', ''));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function branchOptionsForUser(User $user): array
    {
        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return Branch::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => [
                    'id' => (int) $branch->id,
                    'name' => (string) $branch->name,
                ])
                ->all();
        }

        $ids = $user->restrictedBranchIdsForQueries();
        if ($ids === []) {
            return [];
        }

        return Branch::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
            ])
            ->all();
    }

    public function defaultBranchIdForUser(User $user): ?int
    {
        if (filled($user->branch_id)) {
            return (int) $user->branch_id;
        }

        $options = $this->branchOptionsForUser($user);

        return $options[0]['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $validated  Campos del formulario + branch_id
     * @return array{
     *     success: bool,
     *     record: ConciliationBdv|null,
     *     panel: array<string, mixed>,
     *     user_message: string,
     * }
     */
    public function conciliate(array $validated, User $user): array
    {
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        unset($validated['branch_id']);

        $environment = $this->resolveEnvironment();
        $payload = GetMovementRequest::movementPayloadFromValidated($validated);

        try {
            $response = $this->client->postGetMovement($payload, $environment);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->failurePanel(
                operationLabel: 'Conciliación Pagomóvil',
                userMessage: $e->getMessage(),
                errorKind: 'Solicitud no válida',
            );
        } catch (ConnectionException $e) {
            return $this->failurePanel(
                operationLabel: 'Conciliación Pagomóvil',
                userMessage: 'No se alcanzó el servicio BDV: '.$e->getMessage(),
                errorKind: 'Error de conexión',
            );
        }

        $success = $this->isSuccessful($response);
        $record = $this->persistRecord(
            branchId: $branchId,
            user: $user,
            candidate: $payload,
            response: $response,
            environment: $environment,
        );

        $panel = $this->buildPanelFromResponse($response, 'Conciliación Pagomóvil', $success);
        $userMessage = $success
            ? ($this->extractSuccessMessage($response) ?? 'Pago conciliado correctamente con BDV.')
            : $this->extractFailureMessage($response);

        return [
            'success' => $success,
            'record' => $record,
            'panel' => $panel,
            'user_message' => $userMessage,
        ];
    }

    /**
     * @return array{success: bool, record: null, panel: array<string, mixed>, user_message: string}
     */
    private function failurePanel(string $operationLabel, string $userMessage, string $errorKind): array
    {
        return [
            'success' => false,
            'record' => null,
            'panel' => [
                'operation' => $operationLabel,
                'upstream_http_status' => null,
                'upstream_successful' => false,
                'outcome' => 'error',
                'highlight_codes' => [
                    ['key' => 'excepción', 'value' => $errorKind],
                ],
                'body' => $userMessage,
                'body_is_json' => false,
            ],
            'user_message' => $userMessage,
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function persistRecord(
        ?int $branchId,
        User $user,
        array $candidate,
        Response $response,
        string $environment,
    ): ?ConciliationBdv {
        if ($branchId === null || $branchId <= 0) {
            return null;
        }

        try {
            $responseData = $response->json();
            if (! is_array($responseData)) {
                $responseData = ['raw' => $response->body()];
            }

            $rawCode = $responseData['code'] ?? $responseData['codigo'] ?? null;
            $bdvCode = is_scalar($rawCode) ? (string) $rawCode : null;

            return ConciliationBdv::query()->create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'sale_id' => null,
                'environment' => $environment,
                'payer_document' => (string) ($candidate['cedulaPagador'] ?? ''),
                'payer_phone' => (string) ($candidate['telefonoPagador'] ?? ''),
                'destination_phone' => (string) ($candidate['telefonoDestino'] ?? ''),
                'reference' => (string) ($candidate['referencia'] ?? ''),
                'payment_date' => (string) ($candidate['fechaPago'] ?? now()->toDateString()),
                'amount' => (float) ($candidate['importe'] ?? 0),
                'origin_bank' => (string) ($candidate['bancoOrigen'] ?? ''),
                'req_ced' => (bool) ($candidate['reqCed'] ?? false),
                'bdv_http_status' => $response->status(),
                'bdv_code' => $bdvCode,
                'bdv_message' => $this->extractFailureMessage($response),
                'bdv_payload' => $candidate,
                'bdv_response' => $responseData,
                'conciliated_at' => now(),
                'created_by' => $user->email ?? $user->name ?? 'sistema',
            ]);
        } catch (Throwable $e) {
            Log::error('bdv.pagomovil_conciliation.persist_failed', [
                'message' => $e->getMessage(),
                'branch_id' => $branchId,
                'user_id' => $user->id,
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPanelFromResponse(Response $response, string $operationLabel, bool $success): array
    {
        $decoded = $response->json();
        $decodedArray = is_array($decoded) ? $decoded : null;

        return [
            'operation' => $operationLabel,
            'upstream_http_status' => $response->status(),
            'upstream_successful' => $response->successful(),
            'outcome' => $success ? 'success' : 'error',
            'highlight_codes' => $this->extractHighlightCodes($decodedArray),
            'body' => $decodedArray !== null ? $decodedArray : $response->body(),
            'body_is_json' => $decodedArray !== null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @return list<array{key: string, value: string}>
     */
    private function extractHighlightCodes(?array $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach (['code', 'codigo', 'status', 'message'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                continue;
            }

            $val = $decoded[$key];
            if ($val === null || $val === '') {
                continue;
            }

            if (is_scalar($val)) {
                $out[] = ['key' => $key, 'value' => (string) $val];
            }
        }

        return $out;
    }

    public function isSuccessful(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return true;
        }

        if (isset($decoded['status']) && is_numeric($decoded['status']) && (int) $decoded['status'] >= 400) {
            return false;
        }

        if (isset($decoded['codigo'])) {
            return in_array((string) $decoded['codigo'], ['00', '01'], true);
        }

        if (! isset($decoded['code'])) {
            return true;
        }

        $code = $decoded['code'];
        if (in_array($code, ['1000', '200', '00'], true)) {
            return true;
        }

        $asInt = is_int($code) ? $code : (is_numeric($code) ? (int) $code : null);

        return $asInt !== null && in_array($asInt, [1000, 200], true);
    }

    private function extractSuccessMessage(Response $response): ?string
    {
        $decoded = $response->json();
        if (! is_array($decoded)) {
            return null;
        }

        foreach (['message', 'descripcion'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
                return trim($decoded[$key]);
            }
        }

        if (isset($decoded['data']['reason']) && is_string($decoded['data']['reason'])) {
            return trim($decoded['data']['reason']);
        }

        return null;
    }

    private function extractFailureMessage(Response $response): string
    {
        $decoded = $response->json();
        if (is_array($decoded)) {
            foreach (['message', 'error', 'descripcion', 'detalle'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
                    return trim($decoded[$key]);
                }
            }

            if (isset($decoded['code']) || isset($decoded['codigo'])) {
                $code = (string) ($decoded['code'] ?? $decoded['codigo']);

                return 'BDV devolvió código '.$code.' en la conciliación.';
            }
        }

        return 'El banco no confirmó la conciliación del Pago Móvil (HTTP '.$response->status().').';
    }
}
