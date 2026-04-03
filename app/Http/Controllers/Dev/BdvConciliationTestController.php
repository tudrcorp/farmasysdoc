<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Services\BdvConciliation\BdvConciliationClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class BdvConciliationTestController extends Controller
{
    /**
     * Resumen del API según manual MDU-006 (un solo servicio: getMovement).
     */
    public function index(): JsonResponse
    {
        $qa = config('bdv_conciliation.environments.qa', []);
        $prod = config('bdv_conciliation.environments.production', []);

        return response()->json([
            'document' => 'API Conciliación BDV — MDU-006 (manual enero 2024)',
            'note' => 'El manual describe un único endpoint: POST /getMovement (conciliación de pagos móvil).',
            'header' => [
                'X-API-Key' => 'Clave de calidad (manual) o la generada en BDVenLínea Empresa (producción)',
                'Content-Type' => 'application/json',
            ],
            'environments' => [
                'qa' => [
                    'key' => 'qa',
                    'label' => $qa['label'] ?? 'Calidad',
                    'base_url' => $qa['base_url'] ?? null,
                    'path' => '/getMovement',
                ],
                'production' => [
                    'key' => 'production',
                    'label' => $prod['label'] ?? 'Producción',
                    'base_url' => $prod['base_url'] ?? null,
                    'path' => '/getMovement',
                ],
            ],
            'dev_routes' => [
                'GET '.$this->devUrl('dev/bdv-conciliation') => 'Esta ayuda (JSON)',
                'POST '.$this->devUrl('dev/bdv-conciliation/get-movement').'?environment=qa|production' => 'Reenvía el cuerpo JSON validado al BDV (recomendado)',
                'GET '.$this->devUrl('dev/bdv-conciliation/get-movement') => 'Sin query: instrucciones. Con los 7 campos en query + environment: mismo efecto que POST',
                'GET '.$this->devUrl('dev/bdv-conciliation/try-sample-qa') => 'Ejecuta el JSON de prueba del manual contra calidad',
            ],
            'sample_request_body' => $this->samplePayloadFromManual(),
            'business_rules' => [
                'Código 1000 en la respuesta JSON del banco indica éxito; cualquier otro code se trata como error de negocio.',
                'fechaPago: formato YYYY-MM-DD (no usar /).',
                'importe: usar punto decimal (no coma).',
            ],
        ]);
    }

    /**
     * GET /get-movement — el navegador no puede hacer POST: respondemos ayuda o, si vienen todos los parámetros en query, proxy al BDV.
     */
    public function getMovementGet(Request $request, BdvConciliationClient $client): JsonResponse
    {
        $fieldKeys = [
            'cedulaPagador',
            'telefonoPagador',
            'telefonoDestino',
            'referencia',
            'fechaPago',
            'importe',
            'bancoOrigen',
        ];

        $only = $request->only($fieldKeys);
        $anyFilled = false;
        foreach ($fieldKeys as $key) {
            if (filled($only[$key] ?? null)) {
                $anyFilled = true;

                break;
            }
        }

        if (! $anyFilled) {
            return response()->json([
                'message' => 'El proxy hacia BDV usa POST con JSON. Abrir esta URL en el navegador envía GET, por eso ves esta respuesta.',
                'use_post' => [
                    'method' => 'POST',
                    'url' => url('/dev/bdv-conciliation/get-movement').'?environment=qa',
                    'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                    'body' => $this->samplePayloadFromManual(),
                ],
                'alternatives' => [
                    'get_try_sample' => url('/dev/bdv-conciliation/try-sample-qa'),
                    'get_with_full_query' => 'Misma ruta GET con query: '.implode(', ', $fieldKeys).' y opcional environment=qa|production',
                ],
            ]);
        }

        $validator = Validator::make(
            $only,
            (new GetMovementRequest)->rules(),
            (new GetMovementRequest)->messages(),
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Faltan o son inválidos algunos parámetros en la query (se requieren los 7 campos del manual).',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var array<string, string> $validated */
        $validated = $validator->validated();
        $environment = strtolower((string) $request->query('environment', 'qa'));
        $payload = GetMovementRequest::movementPayloadFromValidated($validated);

        return $this->forwardGetMovementToBdv($client, $payload, $environment);
    }

    /**
     * POST cuerpo JSON → BDV getMovement.
     */
    public function getMovement(GetMovementRequest $request, BdvConciliationClient $client): JsonResponse
    {
        $environment = strtolower((string) $request->query('environment', 'qa'));

        return $this->forwardGetMovementToBdv($client, $request->movementPayload(), $environment);
    }

    /**
     * GET — dispara el ejemplo satisfactorio del manual contra ambiente calidad.
     */
    public function trySampleQa(BdvConciliationClient $client): JsonResponse
    {
        try {
            $response = $client->postGetMovement($this->samplePayloadFromManual(), 'qa');
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'No se pudo conectar con el servicio BDV (calidad).',
                'detail' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al llamar al API de conciliación.',
                'detail' => $e->getMessage(),
            ], 500);
        }

        return $this->jsonFromBdvResponse($response);
    }

    /**
     * @return array<string, string>
     */
    private function samplePayloadFromManual(): array
    {
        return [
            'cedulaPagador' => 'V27037606',
            'telefonoPagador' => '04127141363',
            'telefonoDestino' => '04127141363',
            'referencia' => '123112313',
            'fechaPago' => '2023-02-12',
            'importe' => '120.00',
            'bancoOrigen' => '0102',
        ];
    }

    private function devUrl(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function forwardGetMovementToBdv(BdvConciliationClient $client, array $payload, string $environment): JsonResponse
    {
        try {
            $response = $client->postGetMovement($payload, $environment);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'No se pudo conectar con el servicio BDV.',
                'detail' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al llamar al API de conciliación.',
                'detail' => $e->getMessage(),
            ], 500);
        }

        return $this->jsonFromBdvResponse($response);
    }

    private function jsonFromBdvResponse(Response $response): JsonResponse
    {
        $decoded = $response->json();

        $payload = [
            'upstream_http_status' => $response->status(),
            'upstream_successful' => $response->successful(),
            'body' => is_array($decoded) ? $decoded : $response->body(),
        ];

        if (! is_array($decoded)) {
            $payload['body_is_json'] = false;
        }

        return response()->json($payload, 200);
    }
}
