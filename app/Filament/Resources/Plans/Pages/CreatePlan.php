<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Services\Partners\PlanCostStructureSynchronizer;
use App\Services\Partners\PlanMaintenance;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    /**
     * Crea el plan y su estructura de costos antes de que se guarden las asignaciones a aliados
     * (que pueden apuntar a subplanes recién creados).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $subplans = (array) ($data['subplans'] ?? []);
        $bands = (array) ($data['affiliate_bands'] ?? []);
        unset($data['subplans'], $data['affiliate_bands']);

        $plan = parent::handleRecordCreation($data);

        if ($plan instanceof Plan) {
            app(PlanCostStructureSynchronizer::class)->sync($plan, $subplans, $bands);
        }

        return $plan;
    }

    protected function afterCreate(): void
    {
        $plan = $this->getRecord();

        if ($plan instanceof Plan) {
            app(PlanMaintenance::class)->normalizePremiumMode($plan);
        }
    }

    protected function getRedirectUrl(): string
    {
        return PlanResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
