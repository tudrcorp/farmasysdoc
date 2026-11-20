<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompany;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPartnerCompany extends ViewRecord
{
    protected static string $resource = PartnerCompanyResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $model = $this->getRecord();
        if ($model instanceof PartnerCompany) {
            $model->loadMissing(['alliedPanelUsers', 'branches', 'contacts', 'documents', 'currentAgreement', 'uncoveredMedicineConnection']);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageUncoveredMedicines')
                ->label('Gestión de medicamentos')
                ->icon(Heroicon::Beaker)
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord() instanceof PartnerCompany && $this->getRecord()->hasUncoveredMedicinesAccess())
                ->url(fn (): string => PartnerCompanyResource::getUrl('uncovered-medicines', ['record' => $this->getRecord()])),
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
