<?php

namespace App\Filament\Resources\PartnerAgreements\Pages;

use App\Filament\Resources\PartnerAgreements\Actions\PartnerAgreementActions;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;
use App\Services\Partners\PartnerAgreementException;
use App\Services\Partners\PartnerAgreementWorkflow;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditPartnerCompanyAgreement extends EditRecord
{
    protected static string $resource = PartnerCompanyAgreementResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $record instanceof PartnerCompanyAgreement, 403);

        try {
            return app(PartnerAgreementWorkflow::class)->updateDraft($record, $data, $user);
        } catch (PartnerAgreementException $exception) {
            Notification::make()->title('No se pudo guardar el convenio')->body($exception->getMessage())->danger()->persistent()->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            PartnerAgreementActions::cancel(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PartnerCompanyAgreementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
