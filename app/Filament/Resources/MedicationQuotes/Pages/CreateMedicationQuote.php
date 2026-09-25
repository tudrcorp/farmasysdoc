<?php

namespace App\Filament\Resources\MedicationQuotes\Pages;

use App\Filament\Resources\MedicationQuotes\MedicationQuoteResource;
use App\Filament\Resources\MedicationQuotes\Pages\Concerns\SavesMedicationQuote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicationQuote extends CreateRecord
{
    use SavesMedicationQuote;

    protected static string $resource = MedicationQuoteResource::class;

    protected static ?string $title = 'Nueva cotización';

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar cotización')
            ->action('askBeforeCreate');
    }

    public function askBeforeCreate(): void
    {
        $this->form->getState();
        $this->mountAction('confirmQuoteDelivery');
    }

    protected function saveQuoteFromDeliveryPrompt(): void
    {
        $this->create();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function afterCreate(): void
    {
        $this->rememberQuoteTotals($this->getRecord());
        $this->deliverQuoteIfRequested($this->getRecord());
    }
}
