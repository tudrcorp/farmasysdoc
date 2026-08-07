<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Pages;

use App\Filament\Resources\ClientDiscounts\Groups\ClientDiscountGroupResource;
use App\Models\ClientDiscountGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewClientDiscountGroup extends ViewRecord
{
    protected static string $resource = ClientDiscountGroupResource::class;

    protected static ?string $title = 'Grupo de descuento';

    protected function resolveRecord(int|string $key): Model
    {
        /** @var ClientDiscountGroup $record */
        $record = parent::resolveRecord($key);
        $record->load(['clients'])->loadCount('clients');

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar grupo')
                ->icon(Heroicon::PencilSquare)
                ->color('primary'),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon(Heroicon::Trash)
                ->modalHeading('Eliminar grupo de descuento')
                ->modalDescription('Los clientes del grupo quedarán sin este descuento (no se eliminan los clientes).'),
        ];
    }
}
