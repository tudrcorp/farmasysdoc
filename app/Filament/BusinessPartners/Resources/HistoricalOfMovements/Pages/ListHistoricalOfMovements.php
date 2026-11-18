<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Pages;

use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\HistoricalOfMovementResource;
use App\Filament\BusinessPartners\Widgets\PartnerCreditSummaryWidget;
use Filament\Resources\Pages\ListRecords;

class ListHistoricalOfMovements extends ListRecords
{
    protected static string $resource = HistoricalOfMovementResource::class;

    public function getTitle(): string
    {
        return 'Estado de cuenta';
    }

    public function getSubheading(): ?string
    {
        return 'Consumo de su línea de crédito, pedido por pedido.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PartnerCreditSummaryWidget::class,
        ];
    }
}
