<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Pages;

use App\Filament\Resources\ClientDiscounts\Individual\IndividualClientDiscountResource;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIndividualClientDiscount extends ViewRecord
{
    protected static string $resource = IndividualClientDiscountResource::class;

    protected static ?string $title = 'Descuento individual';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar descuento')
                ->icon(Heroicon::PencilSquare)
                ->color('primary'),
            Action::make('clearDiscount')
                ->label('Quitar descuento')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Quitar descuento individual')
                ->modalDescription('El cliente dejará de tener descuento en caja. No se elimina el cliente.')
                ->modalSubmitActionLabel('Sí, quitar descuento')
                ->action(function (): void {
                    app(ClientCommercialDiscountAssigner::class)->clearIndividual($this->getRecord());

                    Notification::make()
                        ->title('Descuento individual eliminado')
                        ->success()
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
