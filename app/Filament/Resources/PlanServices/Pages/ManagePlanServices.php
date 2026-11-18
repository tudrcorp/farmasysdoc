<?php

namespace App\Filament\Resources\PlanServices\Pages;

use App\Filament\Resources\PlanServices\PlanServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ManagePlanServices extends ManageRecords
{
    protected static string $resource = PlanServiceResource::class;

    protected static ?string $title = 'Servicios de planes';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Maestro de servicios que el analista asocia a cada plan. Un servicio en uso no se elimina: se desactiva.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo servicio')
                ->icon(Heroicon::Plus),
        ];
    }
}
