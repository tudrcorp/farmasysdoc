<?php

namespace App\Filament\BusinessPartners\Resources\PlanAffiliates\Pages;

use App\Filament\BusinessPartners\Resources\PlanAffiliates\PlanAffiliateResource;
use Filament\Resources\Pages\ListRecords;

class ListPlanAffiliates extends ListRecords
{
    protected static string $resource = PlanAffiliateResource::class;

    public function getTitle(): string
    {
        return 'Afiliados';
    }

    public function getSubheading(): ?string
    {
        return 'Personas de su empresa que pueden comprar en Farmadoc con cargo al convenio.';
    }
}
