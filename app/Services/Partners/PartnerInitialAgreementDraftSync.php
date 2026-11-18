<?php

namespace App\Services\Partners;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;

/**
 * Arranca el histórico de convenios de un aliado que todavía no opera con convenios:
 * con la fecha de creación y la de vencimiento de la ficha crea (o actualiza) el
 * «Convenio inicial» en borrador, para que el analista adjunte el contrato y lo envíe
 * a aprobación por el flujo normal de {@see PartnerAgreementWorkflow}.
 */
class PartnerInitialAgreementDraftSync
{
    /**
     * Campos de la ficha del aliado que se copian al borrador.
     *
     * @var list<string>
     */
    public const SOURCE_FIELDS = [
        'date_created',
        'agreement_expires_on',
        'payment_modality',
        'credit_limit_usd',
        'discount_percentage',
        'profit_percentage_a',
        'agreement_terms',
    ];

    public function __construct(private PartnerAgreementWorkflow $workflow) {}

    /**
     * @return array{outcome: 'created'|'updated'|'skipped'|'failed', agreement: ?PartnerCompanyAgreement, message: ?string}
     */
    public function sync(PartnerCompany $partner, User $actor): array
    {
        if ($partner->isGovernedByAgreements() || $partner->date_created === null || $partner->agreement_expires_on === null) {
            return ['outcome' => 'skipped', 'agreement' => null, 'message' => null];
        }

        $agreements = $partner->agreements()->get();
        $draft = $agreements->first(fn (PartnerCompanyAgreement $agreement): bool => $agreement->type === PartnerAgreementType::Initial
            && $agreement->status->isEditable());

        if ($draft === null && $agreements->contains(fn (PartnerCompanyAgreement $agreement): bool => ! in_array($agreement->status, [PartnerAgreementStatus::Cancelled], true))) {
            return ['outcome' => 'skipped', 'agreement' => null, 'message' => null];
        }

        $data = [
            'partner_company_id' => $partner->getKey(),
            'type' => PartnerAgreementType::Initial,
            'starts_on' => $partner->date_created->toDateString(),
            'ends_on' => $partner->agreement_expires_on->toDateString(),
            'payment_modality' => $partner->payment_modality,
            'credit_limit_usd' => $partner->credit_limit_usd,
            'discount_percentage' => $partner->discount_percentage,
            'profit_percentage_a' => $partner->profit_percentage_a,
            'terms' => $partner->agreement_terms,
        ];

        try {
            if ($draft instanceof PartnerCompanyAgreement) {
                return ['outcome' => 'updated', 'agreement' => $this->workflow->updateDraft($draft, $data, $actor), 'message' => null];
            }

            return ['outcome' => 'created', 'agreement' => $this->workflow->createDraft($data, $actor), 'message' => null];
        } catch (PartnerAgreementException $exception) {
            return ['outcome' => 'failed', 'agreement' => $draft, 'message' => $exception->getMessage()];
        }
    }
}
