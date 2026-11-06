<?php

namespace App\Filament\Resources\PartnerAgreements\Pages;

use App\Filament\Resources\PartnerAgreements\Actions\PartnerAgreementActions;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Models\PartnerCompanyAgreement;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPartnerCompanyAgreement extends ViewRecord
{
    protected static string $resource = PartnerCompanyAgreementResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'partnerCompany',
            'previousAgreement',
            'nextAgreement',
            'creator',
            'submitter',
            'approver',
            'rejecter',
            'canceller',
            'events.user',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof PartnerCompanyAgreement ? $record->displayTitle() : parent::getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...PartnerAgreementActions::all(),
            PartnerAgreementActions::viewContract(),
            EditAction::make()
                ->visible(fn (PartnerCompanyAgreement $record): bool => $record->status->isEditable()),
        ];
    }
}
