<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Pages;

use App\Filament\Resources\ClientDiscounts\Individual\IndividualClientDiscountResource;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditIndividualClientDiscount extends EditRecord
{
    protected static string $resource = IndividualClientDiscountResource::class;

    protected static ?string $title = 'Editar descuento individual';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearDiscount')
                ->label('Quitar descuento')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Quitar descuento individual')
                ->modalDescription('El cliente dejará de tener descuento en caja. No se elimina el cliente.')
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['client_name'] = $this->getRecord()->name
            .(filled($this->getRecord()->document_number) ? ' · '.$this->getRecord()->document_number : '');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $percent = (float) ($data['customer_discount'] ?? 0);

        app(ClientCommercialDiscountAssigner::class)->assignIndividual($record, $percent);

        return $record->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
