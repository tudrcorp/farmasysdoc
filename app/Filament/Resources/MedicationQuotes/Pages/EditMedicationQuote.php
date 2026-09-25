<?php

namespace App\Filament\Resources\MedicationQuotes\Pages;

use App\Filament\Resources\MedicationQuotes\MedicationQuoteResource;
use App\Filament\Resources\MedicationQuotes\Pages\Concerns\SavesMedicationQuote;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMedicationQuote extends EditRecord
{
    use SavesMedicationQuote;

    protected static string $resource = MedicationQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Guardar cotización')
            ->action('askBeforeSave');
    }

    public function askBeforeSave(): void
    {
        $this->form->getState();
        $this->mountAction('confirmQuoteDelivery');
    }

    protected function saveQuoteFromDeliveryPrompt(): void
    {
        $this->save();
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function afterSave(): void
    {
        $this->rememberQuoteTotals($this->getRecord());
        $this->deliverQuoteIfRequested($this->getRecord());
    }
}
