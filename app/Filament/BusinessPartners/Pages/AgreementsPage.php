<?php

namespace App\Filament\BusinessPartners\Pages;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerPanelPermission;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Support\Partners\AgreementTermStatus;
use App\Support\Partners\PartnerPanel;
use App\Support\Partners\PartnerPlanCards;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * «Planes y convenios» del aliado: vigencia, condiciones del convenio vigente, planes contratados
 * e historial de convenios con descarga del contrato firmado. Solo lectura.
 */
class AgreementsPage extends Page
{
    /**
     * Estados que el aliado ve en su historial (los borradores y trámites internos no se muestran).
     *
     * @var list<PartnerAgreementStatus>
     */
    public const VISIBLE_STATUSES = [
        PartnerAgreementStatus::Approved,
        PartnerAgreementStatus::Active,
        PartnerAgreementStatus::Expired,
        PartnerAgreementStatus::Superseded,
    ];

    protected static ?string $slug = 'planes-y-convenios';

    protected static ?string $title = 'Planes y convenios';

    protected static ?string $navigationLabel = 'Planes y convenios';

    protected static string|UnitEnum|null $navigationGroup = 'Mi gestión';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;

    protected string $view = 'filament.business-partners.pages.agreements';

    public static function canAccess(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::PlansAndAgreements);
    }

    public function getSubheading(): ?string
    {
        return 'Su convenio con Farmadoc: vigencia, condiciones, planes contratados e historial.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $partner = $this->partner();
        $current = $partner->currentAgreement;

        $term = $partner->agreementTermStatus();
        $history = $this->history($partner);

        return [
            'term' => $term,
            'remainingPercent' => $term->progressPercent() === null ? null : 100 - $term->progressPercent(),
            'current' => collect($history)->firstWhere('is_current'),
            'conditions' => $this->conditions($partner, $current),
            'planCards' => PartnerPlanCards::for($partner),
            'history' => $history,
        ];
    }

    public function downloadContract(int $agreementId): ?StreamedResponse
    {
        $agreement = $this->visibleAgreements($this->partner())->firstWhere('id', $agreementId);

        if (! $agreement instanceof PartnerCompanyAgreement || ! $agreement->hasSignedDocument()) {
            Notification::make()->title('El contrato no está disponible.')->danger()->send();

            return null;
        }

        return Storage::disk(PartnerCompanyAgreement::documentDisk())->download(
            (string) $agreement->signed_document_path,
            $agreement->signed_document_name ?: 'convenio-'.($agreement->number ?? $agreement->getKey()).'.pdf',
        );
    }

    private function partner(): PartnerCompany
    {
        $partner = PartnerCompany::query()->with('currentAgreement')->find(PartnerPanel::companyId());

        abort_unless($partner instanceof PartnerCompany, 403);

        return $partner;
    }

    /**
     * @return Collection<int, PartnerCompanyAgreement>
     */
    private function visibleAgreements(PartnerCompany $partner): Collection
    {
        return $partner->agreements()
            ->whereIn('status', array_map(fn (PartnerAgreementStatus $status): string => $status->value, self::VISIBLE_STATUSES))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return list<array{label: string, value: string, icon: string}>
     */
    private function conditions(PartnerCompany $partner, ?PartnerCompanyAgreement $current): array
    {
        $modality = $current?->payment_modality ?? $partner->payment_modality;
        $creditLimit = $current?->credit_limit_usd ?? $partner->credit_limit_usd;
        $discount = $current?->discount_percentage ?? $partner->discount_percentage;

        $conditions = [
            ['label' => 'Modalidad de pago', 'value' => $modality?->label() ?? '—', 'icon' => 'heroicon-o-credit-card'],
        ];

        if ($modality?->usesCreditLimit()) {
            $conditions[] = ['label' => 'Límite de crédito', 'value' => $creditLimit === null ? '—' : '$'.number_format((float) $creditLimit, 2, ',', '.'), 'icon' => 'heroicon-o-banknotes'];
        }

        if ($discount !== null && (float) $discount > 0) {
            $conditions[] = ['label' => 'Descuento pactado', 'value' => rtrim(rtrim(number_format((float) $discount, 2, ',', '.'), '0'), ',').'%', 'icon' => 'heroicon-o-receipt-percent'];
        }

        if ($current?->auto_renews) {
            $conditions[] = ['label' => 'Renovación', 'value' => 'Automática', 'icon' => 'heroicon-o-arrow-path'];
        }

        return $conditions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(PartnerCompany $partner): array
    {
        $currentId = $partner->currentAgreement?->getKey();

        return $this->visibleAgreements($partner)
            ->map(function (PartnerCompanyAgreement $agreement) use ($currentId): array {
                $term = AgreementTermStatus::fromDates($agreement->starts_on, $agreement->ends_on);

                return [
                    'id' => (int) $agreement->getKey(),
                    'number' => $agreement->number ?? 'Sin número',
                    'type' => $agreement->type->label(),
                    'status' => $agreement->status->label(),
                    'status_color' => $agreement->status->color(),
                    'is_current' => $agreement->getKey() === $currentId,
                    'period' => $agreement->starts_on?->format('d/m/Y').' al '.$agreement->ends_on?->format('d/m/Y'),
                    'days' => $term->totalDays === null ? null : number_format($term->totalDays, 0, ',', '.').' días',
                    'has_contract' => filled($agreement->signed_document_path),
                    'signed_on' => $agreement->signed_on?->format('d/m/Y'),
                ];
            })
            ->values()
            ->all();
    }
}
