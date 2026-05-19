<?php

namespace App\Services\BdvConciliation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Adaptador específico del servicio de conciliación de Pagomóvil (getMovement / v2).
 *
 * Delega el HTTP en {@see BdvBankApiClient} usando la ranura de credencial `conciliation`
 * y el path configurado en `bdv_conciliation.paths.get_movement` (por defecto /getMovement/v2).
 */
class BdvConciliationClient
{
    public function __construct(
        private readonly BdvBankApiClient $bankApi,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Debe incluir los campos del manual (incl. reqCed boolean si aplica)
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ConnectionException
     */
    public function postGetMovement(array $payload, string $environment = 'qa'): Response
    {
        if (
            ! app()->environment('production')
            && app()->environment('local')
            && filter_var(config('bdv_conciliation.fake_ok_enabled'), FILTER_VALIDATE_BOOL)
        ) {
            $amount = number_format(max(0.0, (float) ($payload['importe'] ?? 0)), 2, '.', '');
            $reference = preg_replace('/\D+/', '', (string) ($payload['referencia'] ?? '')) ?? '';
            if ($reference === '') {
                $reference = now()->format('His');
            }

            return Http::response([
                'code' => 1000,
                'message' => 'SIMULADO QA/LOCAL: monto : '.$amount.' - estatus : Transacción realizada',
                'data' => [
                    'status' => '1000',
                    'amount' => $amount,
                    'reason' => 'Transacción realizada',
                    'referencia' => $reference,
                ],
                'status' => 200,
            ], 200);
        }

        $path = $this->bankApi->path('get_movement');

        return $this->bankApi->postJson($path, $payload, $environment, 'conciliation');
    }
}
