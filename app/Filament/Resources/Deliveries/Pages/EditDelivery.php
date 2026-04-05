<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDelivery extends EditRecord
{
    protected static string $resource = DeliveryResource::class;

    protected static ?string $title = 'Editar entrega';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver ficha')
                ->icon(Heroicon::Eye),
            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('Eliminar entrega')
                ->modalDescription('¿Seguro? Esta acción no elimina el pedido asociado.')
                ->modalSubmitActionLabel('Sí, eliminar'),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Guardar cambios')
            ->icon(Heroicon::Check)
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ]);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Cancelar');
    }
}
