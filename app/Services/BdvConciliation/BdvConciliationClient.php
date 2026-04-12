<?php

namespace App\Services\BdvConciliation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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
        $path = $this->bankApi->path('get_movement');

        return $this->bankApi->postJson($path, $payload, $environment, 'conciliation');
    }
}
