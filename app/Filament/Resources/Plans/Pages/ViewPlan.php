<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\Actions\PlanActions;
use App\Filament\Resources\Plans\PlanResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPlan extends ViewRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                PlanActions::duplicate(),
                PlanActions::refreshVesPremiums(),
            ]),
        ];
    }
}
