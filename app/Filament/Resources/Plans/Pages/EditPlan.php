<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Services\Partners\PlanCostStructureSynchronizer;
use App\Services\Partners\PlanMaintenance;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $plan = $this->getRecord();

        return $plan instanceof Plan
            ? [...$data, ...app(PlanCostStructureSynchronizer::class)->toFormState($plan)]
            : $data;
    }

    protected function beforeSave(): void
    {
        $plan = $this->getRecord();

        if (! $plan instanceof Plan) {
            return;
        }

        $reason = app(PlanCostStructureSynchronizer::class)->blockingReason($plan, $this->data ?? []);

        if ($reason !== null) {
            Notification::make()
                ->title('No se guardaron los cambios')
                ->body($reason)
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        $this->syncCostStructure($plan);
    }

    /**
     * Filament guarda las asignaciones a aliados justo después de este punto (dentro de la
     * validación), así que la modalidad, los subplanes y el baremo se guardan aquí para que
     * una asignación pueda apuntar a un subplan recién agregado.
     */
    private function syncCostStructure(Plan $plan): void
    {
        $state = $this->data ?? [];

        $plan->forceFill([
            'premium_mode' => $state['premium_mode'] ?? $plan->premium_mode,
            'has_subplans' => (bool) ($state['has_subplans'] ?? false),
        ])->save();

        app(PlanCostStructureSynchronizer::class)->sync(
            $plan,
            (array) ($state['subplans'] ?? []),
            (array) ($state['affiliate_bands'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        unset($data['subplans'], $data['affiliate_bands']);

        return parent::handleRecordUpdate($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterSave(): void
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
