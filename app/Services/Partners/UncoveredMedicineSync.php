<?php

namespace App\Services\Partners;

use App\Enums\UncoveredMedicineSyncStatus;
use App\Jobs\SyncPartnerUncoveredMedicinesJob;
use App\Models\PartnerCompany;
use App\Models\PartnerUncoveredMedicine;
use App\Models\PartnerUncoveredMedicineConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Carga completa de medicamentos no cubiertos. No usa solo la fecha de actualización:
 * el estado de inventario cambia sin mover `actualizado_en`.
 *
 * Si la carga se interrumpe, la copia local anterior se conserva.
 */
class UncoveredMedicineSync
{
    public function __construct(
        private readonly UncoveredMedicineClient $client,
    ) {}

    /**
     * Reserva la actualización y encola el job. Devuelve false si ya hay una en curso.
     */
    public function queue(PartnerCompany $company, ?int $requestedByUserId): bool
    {
        $connection = $company->uncoveredMedicineConnection;
        if (! $connection instanceof PartnerUncoveredMedicineConnection || ! $connection->hasToken()) {
            throw new UncoveredMedicineApiException('Este aliado no tiene token de Tu Dr. Group.');
        }

        if ($connection->isSyncActive()) {
            return false;
        }

        $runId = (string) Str::uuid();
        $lockKey = PartnerUncoveredMedicineConnection::queueCacheKey((int) $company->getKey());
        $lockMinutes = PartnerUncoveredMedicineConnection::staleRunningMinutes();

        if (! Cache::add($lockKey, $runId, now()->addMinutes($lockMinutes))) {
            $startedAt = $connection->sync_started_at;
            $stale = $startedAt === null || $startedAt->lt(now()->subMinutes($lockMinutes));
            if (! $stale) {
                return false;
            }

            Cache::forget($lockKey);
            if (! Cache::add($lockKey, $runId, now()->addMinutes($lockMinutes))) {
                return false;
            }
        }

        $connection->forceFill([
            'sync_status' => UncoveredMedicineSyncStatus::Running,
            'sync_run_id' => $runId,
            'sync_started_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            SyncPartnerUncoveredMedicinesJob::dispatch(
                (int) $company->getKey(),
                $runId,
                $requestedByUserId,
            );
        } catch (Throwable $exception) {
            $this->markFailed($connection, $runId, 'No se pudo encolar la actualización. Intente de nuevo.');
            PartnerUncoveredMedicineConnection::releaseSyncLock((int) $company->getKey(), $runId);

            throw $exception;
        }

        return true;
    }

    /**
     * Descarga todas las páginas y reemplaza la copia local solo si la carga termina.
     */
    public function sync(PartnerCompany $company, string $runId): int
    {
        $connection = $company->uncoveredMedicineConnection;
        if (! $connection instanceof PartnerUncoveredMedicineConnection) {
            return 0;
        }

        if ($connection->sync_run_id !== $runId) {
            return 0;
        }

        if (! $connection->hasToken()) {
            $this->markFailed($connection, $runId, 'Este aliado no tiene token de Tu Dr. Group.');

            throw new UncoveredMedicineApiException('Este aliado no tiene token de Tu Dr. Group.');
        }

        try {
            $token = (string) $connection->token;
            $profile = $this->withRateLimitRetry(fn (): array => $this->client->profile($token));
            $this->rememberProfile($connection, $runId, $profile);

            $perPage = min(100, max(1, (int) config('partner_uncovered_medicines.per_page', 100)));
            $maxPages = max(1, (int) config('partner_uncovered_medicines.max_pages', 500));
            $page = 1;
            $lastPage = 1;
            /** @var array<int, array<string, mixed>> $rows */
            $rows = [];
            $rateLimit = null;
            $rateRemaining = null;

            do {
                $result = $this->fetchPage($token, $page, $perPage);
                $rateLimit = $result->rateLimitLimit ?? $rateLimit;
                $rateRemaining = $result->rateLimitRemaining ?? $rateRemaining;

                foreach ($result->rows as $row) {
                    $mapped = $this->mapRow($row);
                    if ($mapped !== null) {
                        $rows[$mapped['external_id']] = $mapped;
                    }
                }

                $lastPage = $result->lastPage;
                $page++;

                if ($page <= $lastPage && $rateRemaining !== null && $rateRemaining < 1) {
                    sleep(60);
                }
            } while ($page <= $lastPage && $page <= $maxPages);

            if ($lastPage > $maxPages) {
                throw new UncoveredMedicineApiException('La consulta superó el máximo de páginas. No se reemplazó la copia local.');
            }

            $syncedAt = CarbonImmutable::now();

            return DB::transaction(function () use ($connection, $runId, $company, $rows, $syncedAt, $rateLimit, $rateRemaining, $profile): int {
                $locked = PartnerUncoveredMedicineConnection::query()
                    ->whereKey($connection->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $locked instanceof PartnerUncoveredMedicineConnection || $locked->sync_run_id !== $runId) {
                    return 0;
                }

                $count = 0;
                foreach ($rows as $mapped) {
                    PartnerUncoveredMedicine::query()->updateOrCreate(
                        [
                            'partner_company_id' => $company->getKey(),
                            'external_id' => $mapped['external_id'],
                        ],
                        [
                            ...$mapped,
                            'synced_at' => $syncedAt,
                        ],
                    );
                    $count++;
                }

                $stale = PartnerUncoveredMedicine::query()
                    ->where('partner_company_id', $company->getKey());

                if ($rows === []) {
                    $stale->delete();
                } else {
                    $stale->whereNotIn('external_id', array_keys($rows))->delete();
                }

                $locked->forceFill([
                    'sync_status' => UncoveredMedicineSyncStatus::Succeeded,
                    'last_synced_at' => $syncedAt,
                    'last_error' => null,
                    'rate_limit_limit' => $rateLimit,
                    'rate_limit_remaining' => $rateRemaining,
                    'profile' => $profile,
                ])->save();

                return $count;
            });
        } catch (Throwable $exception) {
            $message = $exception instanceof UncoveredMedicineApiException
                ? $exception->getMessage()
                : 'No se pudo actualizar la información. Intente de nuevo.';

            $this->markFailed($connection, $runId, $message);

            Log::warning('No se pudo sincronizar medicamentos no cubiertos.', [
                'partner_company_id' => $company->getKey(),
                'status' => $exception instanceof UncoveredMedicineApiException ? $exception->status : null,
                'message' => $message,
            ]);

            throw $exception instanceof UncoveredMedicineApiException
                ? $exception
                : new UncoveredMedicineApiException($message);
        }
    }

    private function fetchPage(string $token, int $page, int $perPage): UncoveredMedicinePage
    {
        return $this->withRateLimitRetry(fn (): UncoveredMedicinePage => $this->client->list($token, $page, $perPage));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withRateLimitRetry(callable $callback): mixed
    {
        $retries = max(1, (int) config('partner_uncovered_medicines.rate_limit_retries', 5));
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (UncoveredMedicineApiException $exception) {
                if (! $exception->isRateLimited() || $attempt >= $retries) {
                    throw $exception;
                }

                $attempt++;
                sleep(min(90, max(1, $exception->retryAfterSeconds ?? 60)));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function rememberProfile(PartnerUncoveredMedicineConnection $connection, string $runId, array $profile): void
    {
        $fresh = $connection->fresh();
        if (! $fresh instanceof PartnerUncoveredMedicineConnection || $fresh->sync_run_id !== $runId) {
            return;
        }

        $fresh->forceFill(['profile' => $profile])->save();
    }

    private function markFailed(PartnerUncoveredMedicineConnection $connection, string $runId, string $message): void
    {
        $fresh = $connection->fresh();
        if (! $fresh instanceof PartnerUncoveredMedicineConnection || $fresh->sync_run_id !== $runId) {
            return;
        }

        $fresh->forceFill([
            'sync_status' => UncoveredMedicineSyncStatus::Failed,
            'last_error' => Str::limit($message, 1000),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $externalId = $row['id'] ?? null;
        if (! is_numeric($externalId) || (int) $externalId < 1) {
            return null;
        }

        $case = is_array($row['caso'] ?? null) ? $row['caso'] : [];
        $patient = is_array($row['paciente'] ?? null) ? $row['paciente'] : [];
        $medicine = is_array($row['medicamento'] ?? null) ? $row['medicamento'] : [];
        $inventory = is_array($row['inventario'] ?? null) ? $row['inventario'] : [];

        $medicineName = $this->text($medicine['nombre'] ?? null, 255);

        return [
            'external_id' => (int) $externalId,
            'case_number' => $this->text($case['numero'] ?? null, 255),
            'coordination_id' => is_numeric($case['coordinacion_id'] ?? null) ? (int) $case['coordinacion_id'] : null,
            'coordination_reference' => $this->text($case['coordinacion_referencia'] ?? null, 255),
            'coordination_status' => $this->text($case['coordinacion_estatus'] ?? null, 255),
            'patient_name' => $this->text($patient['nombre'] ?? null, 500),
            'patient_national_id' => $this->text($patient['cedula'] ?? null, 100),
            'patient_phone' => $this->text($patient['telefono'] ?? null, 100),
            'patient_state' => $this->text($patient['estado'] ?? null, 255),
            'patient_city' => $this->text($patient['ciudad'] ?? null, 255),
            'medicine_name' => $medicineName ?? 'Sin nombre',
            'indications' => $this->text($medicine['indicaciones'] ?? null, 5000),
            'quantity_label' => $this->text($medicine['cantidad'] ?? null, 255),
            'duration_label' => $this->text($medicine['duracion'] ?? null, 255),
            'priority' => $this->text($medicine['prioridad'] ?? null, 255),
            'management_status' => $this->text($medicine['estatus_gestion'] ?? null, 255),
            'covered' => (bool) ($medicine['cubierto'] ?? false),
            'inventory_status' => $this->text($inventory['estado'] ?? null, 255),
            'registered_at' => $this->timestamp($row['registrado_en'] ?? null),
            'remote_updated_at' => $this->timestamp($row['actualizado_en'] ?? null),
        ];
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return Str::limit($text, $limit, '');
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
