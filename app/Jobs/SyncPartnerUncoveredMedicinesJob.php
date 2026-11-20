<?php

namespace App\Jobs;

use App\Enums\UncoveredMedicineSyncStatus;
use App\Models\PartnerCompany;
use App\Models\PartnerUncoveredMedicineConnection;
use App\Models\User;
use App\Services\Partners\UncoveredMedicineApiException;
use App\Services\Partners\UncoveredMedicineSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Actualización manual de medicamentos no cubiertos. Los reintentos de cupo ocurren dentro del servicio.
 */
class SyncPartnerUncoveredMedicinesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $partnerCompanyId,
        public string $runId,
        public ?int $requestedByUserId = null,
    ) {
        $this->afterCommit();
    }

    public function handle(UncoveredMedicineSync $sync): void
    {
        $company = PartnerCompany::query()->find($this->partnerCompanyId);
        if (! $company instanceof PartnerCompany) {
            PartnerUncoveredMedicineConnection::releaseSyncLock($this->partnerCompanyId, $this->runId);

            return;
        }

        try {
            $count = $sync->sync($company, $this->runId);
            $company->unsetRelation('uncoveredMedicineConnection');
            $connection = $company->uncoveredMedicineConnection;

            if (
                $connection !== null
                && $connection->sync_run_id === $this->runId
                && $connection->sync_status === UncoveredMedicineSyncStatus::Succeeded
            ) {
                $this->notify(
                    $company,
                    'Medicamentos no cubiertos actualizados',
                    $company->legal_name.': '.$count.' registros en la copia local.',
                    success: true,
                );
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof UncoveredMedicineApiException
                ? $exception->getMessage()
                : 'No se pudo actualizar la información. Intente de nuevo.';

            $this->notify($company, 'No se pudieron actualizar los medicamentos', $message, success: false);
        } finally {
            PartnerUncoveredMedicineConnection::releaseSyncLock($this->partnerCompanyId, $this->runId);
        }
    }

    private function notify(PartnerCompany $company, string $title, string $body, bool $success): void
    {
        $user = $this->requestedByUserId !== null
            ? User::query()->find($this->requestedByUserId)
            : null;

        if (! $user instanceof User) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('open')
                    ->label('Ver medicamentos')
                    ->url(route('filament.farmaadmin.resources.partner-companies.uncovered-medicines', ['record' => $company]))
                    ->markAsRead(),
            ]);

        if ($success) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->sendToDatabase($user);
    }
}
