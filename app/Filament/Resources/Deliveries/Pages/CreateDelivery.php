<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;

class CreateDelivery extends CreateRecord
{
    protected static string $resource = DeliveryResource::class;

    protected static ?string $title = 'Registrar entrega';

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar entrega')
            ->icon(Heroicon::Check)
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
            ]);
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Guardar y registrar otra')
            ->extraAttributes([
                'class' => 'farmadoc-ios-action farmadoc-ios-action--info',
            ]);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Cancelar');
    }
}
