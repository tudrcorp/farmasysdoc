<?php

namespace App\Filament\Resources\PartnerAgreements\Pages;

use App\Enums\PartnerAgreementType;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Filament\Resources\PartnerAgreements\Support\PartnerAgreementFormDefaults;
use App\Models\PartnerCompany;
use App\Models\User;
use App\Services\Partners\PartnerAgreementException;
use App\Services\Partners\PartnerAgreementWorkflow;
use App\Support\Filament\BranchAuthScope;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePartnerCompanyAgreement extends CreateRecord
{
    protected static string $resource = PartnerCompanyAgreementResource::class;

    protected static bool $canCreateAnother = false;

    protected function fillForm(): void
    {
        $partnerId = request()->integer('partner') ?: null;
        $type = PartnerAgreementType::tryFrom((string) request()->query('type')) ?? PartnerAgreementType::Initial;

        if ($partnerId !== null && ! BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())->whereKey($partnerId)->exists()) {
            $partnerId = null;
        }

        $this->form->fill([
            'partner_company_id' => $partnerId,
            'type' => $type->value,
            ...PartnerAgreementFormDefaults::for($partnerId, $type),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $isVisible = BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())
            ->whereKey((int) ($data['partner_company_id'] ?? 0))
            ->exists();
        abort_unless($isVisible, 403);

        try {
            return app(PartnerAgreementWorkflow::class)->createDraft($data, $user);
        } catch (PartnerAgreementException $exception) {
            Notification::make()->title('No se pudo guardar el convenio')->body($exception->getMessage())->danger()->persistent()->send();
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Convenio guardado como borrador. Adjunte el contrato firmado y envíelo a aprobación.';
    }

    protected function getRedirectUrl(): string
    {
        return PartnerCompanyAgreementResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
