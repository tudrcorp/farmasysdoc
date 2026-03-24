<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPartnerCompany extends ViewRecord
{
    protected static string $resource = PartnerCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar Compañía Aliada')
                ->color('primary')
                ->icon(Heroicon::PencilSquare)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
