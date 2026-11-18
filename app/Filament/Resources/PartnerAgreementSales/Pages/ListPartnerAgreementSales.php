<?php

namespace App\Filament\Resources\PartnerAgreementSales\Pages;

use App\Filament\Resources\PartnerAgreementSales\PartnerAgreementSaleResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerAgreementSales extends ListRecords
{
    protected static string $resource = PartnerAgreementSaleResource::class;

    public function getSubheading(): ?string
    {
        return 'Notas de entrega con cargo al fondo de consumo de los aliados (plan Flexible). No son ventas fiscales.';
    }
}
