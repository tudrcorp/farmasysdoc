<?php

namespace App\Filament\Resources\MedicationQuotes\Pages\Concerns;

use App\Filament\Resources\MedicationQuotes\Actions\RegisterQuoteProductAction;
use App\Models\MedicationQuote;
use App\Services\Quotes\MedicationQuoteSender;
use App\Support\Purchases\MedicationQuoteTotals;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

trait SavesMedicationQuote
{
    public bool $sendQuoteOnSave = false;

    public function registerQuoteProductAction(): Action
    {
        return RegisterQuoteProductAction::make();
    }

    public function confirmQuoteDeliveryAction(): Action
    {
        return Action::make('confirmQuoteDelivery')
            ->modalHeading('¿Enviar la cotización?')
            ->modalDescription('El solicitante tiene correo y teléfono registrados. Puede enviarla ahora o solo guardarla.')
            ->modalSubmitActionLabel('Guardar')
            ->schema([
                Toggle::make('send_quote')
                    ->label('Enviar por correo y WhatsApp')
                    ->helperText('Si lo deja apagado, la cotización solo queda guardada.')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                $this->sendQuoteOnSave = (bool) ($data['send_quote'] ?? false);
                $this->saveQuoteFromDeliveryPrompt();
            });
    }

    protected function rememberQuoteTotals(MedicationQuote $quote): void
    {
        $quote->load('lines');
        $totals = MedicationQuoteTotals::calculate(
            $quote->lines->map(fn ($line): array => [
                'quantity' => $line->quantity,
                'unit_price_usd' => $line->unit_price_usd,
            ])->all(),
            $quote->adjustment_kind,
            (float) $quote->adjustment_percent,
        );

        $quote->forceFill([
            'subtotal_usd' => $totals['subtotal_usd'],
            'total_usd' => $totals['total_usd'],
        ])->save();
    }

    protected function deliverQuoteIfRequested(MedicationQuote $quote): void
    {
        if (! $this->sendQuoteOnSave) {
            Notification::make()
                ->title('Cotización guardada')
                ->body('No se envió al solicitante.')
                ->success()
                ->send();

            return;
        }

        $notes = app(MedicationQuoteSender::class)->send($quote->fresh() ?? $quote);

        Notification::make()
            ->title('Cotización guardada')
            ->body(implode(' ', $notes))
            ->success()
            ->send();
    }
}
