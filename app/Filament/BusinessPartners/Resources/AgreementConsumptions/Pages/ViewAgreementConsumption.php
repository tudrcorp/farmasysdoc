<?php

namespace App\Filament\BusinessPartners\Resources\AgreementConsumptions\Pages;

use App\Filament\BusinessPartners\Resources\AgreementConsumptions\AgreementConsumptionResource;
use App\Models\PartnerAgreementSale;
use App\Services\Partners\PartnerDeliveryNotePdfFactory;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAgreementConsumption extends ViewRecord
{
    protected static string $resource = AgreementConsumptionResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var PartnerAgreementSale $sale */
        $sale = $this->getRecord();

        return 'Consumo '.$sale->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadNote')
                ->label('Descargar nota de entrega')
                ->icon(Heroicon::ArrowDownTray)
                ->action(function (): StreamedResponse {
                    /** @var PartnerAgreementSale $sale */
                    $sale = $this->getRecord();
                    $pdf = app(PartnerDeliveryNotePdfFactory::class)->output($sale);

                    return response()->streamDownload(fn () => print ($pdf), $sale->fileName(), ['Content-Type' => 'application/pdf']);
                }),
        ];
    }
}
