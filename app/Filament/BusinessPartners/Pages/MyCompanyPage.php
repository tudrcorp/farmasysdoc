<?php

namespace App\Filament\BusinessPartners\Pages;

use App\Models\PartnerCompany;
use App\Models\PartnerCompanyContact;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Ficha de la empresa aliada en solo lectura: datos fiscales, contacto, responsables y sucursales
 * de Farmadoc donde opera el convenio. La ven todos los usuarios del aliado.
 */
class MyCompanyPage extends Page
{
    protected static ?string $slug = 'mi-empresa';

    protected static ?string $title = 'Mi empresa';

    protected static ?string $navigationLabel = 'Mi empresa';

    /**
     * Sin grupo y justo debajo de «Inicio» (sort -10).
     */
    protected static ?int $navigationSort = -9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingOffice2;

    protected string $view = 'filament.business-partners.pages.my-company';

    public static function canAccess(): bool
    {
        return PartnerPanel::user() !== null;
    }

    public function getSubheading(): ?string
    {
        return 'Información de su empresa registrada en Farmadoc.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $partner = PartnerCompany::query()
            ->with(['contacts', 'branches:id,name', 'currentAgreement'])
            ->withCount('plans')
            ->find(PartnerPanel::companyId());

        abort_unless($partner instanceof PartnerCompany, 403);

        $term = $partner->agreementTermStatus();
        $phones = collect([$partner->phone, $partner->mobile_phone])->filter()->unique()->values();
        $website = filled($partner->website) ? (string) $partner->website : null;

        return [
            'company' => [
                'legal_name' => (string) $partner->legal_name,
                'trade_name' => filled($partner->trade_name) && $partner->trade_name !== $partner->legal_name ? (string) $partner->trade_name : null,
                'initials' => self::initials((string) $partner->legal_name),
                'tax_id' => $partner->tax_id,
                'code' => $partner->code,
                'client_type' => $partner->client_type?->label(),
                'modality' => $partner->payment_modality === null ? null : ['label' => $partner->payment_modality->label(), 'color' => $partner->payment_modality->color()],
                'is_active' => (bool) $partner->is_active,
            ],
            'facts' => [
                ['label' => 'Aliado desde', 'value' => $partner->date_created?->translatedFormat('j M Y') ?? '—', 'icon' => 'heroicon-o-calendar'],
                ['label' => 'Convenio', 'value' => $term->label, 'icon' => 'heroicon-o-document-check', 'tone' => $term->tone],
                ['label' => 'Vence', 'value' => $term->endsOn?->translatedFormat('j M Y') ?? 'Sin fecha', 'icon' => 'heroicon-o-clock'],
                ['label' => 'Planes contratados', 'value' => (string) $partner->plans_count, 'icon' => 'heroicon-o-shield-check'],
            ],
            'contact' => array_values(array_filter([
                filled($partner->email) ? ['label' => 'Correo', 'value' => (string) $partner->email, 'icon' => 'heroicon-o-envelope', 'href' => 'mailto:'.$partner->email] : null,
                ...$phones->map(fn (string $phone, int $index): array => ['label' => $index === 0 ? 'Teléfono' : 'Otro teléfono', 'value' => $phone, 'icon' => 'heroicon-o-phone', 'href' => 'tel:'.preg_replace('/[^\d+]/', '', $phone)])->all(),
                $website !== null ? ['label' => 'Sitio web', 'value' => preg_replace('#^https?://#', '', rtrim($website, '/')), 'icon' => 'heroicon-o-globe-alt', 'href' => str_starts_with($website, 'http') ? $website : 'https://'.$website, 'external' => true] : null,
            ])),
            'address' => collect([$partner->address, $partner->city, $partner->state, $partner->country])->filter()->implode(', ') ?: null,
            'people' => $partner->contacts->map(fn (PartnerCompanyContact $contact): array => [
                'name' => $contact->name,
                'initials' => self::initials((string) $contact->name),
                'position' => $contact->position,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_responsible' => (bool) $contact->is_responsible,
            ])->sortByDesc('is_responsible')->values()->all(),
            'branches' => $partner->branches->pluck('name')->all(),
        ];
    }

    private static function initials(string $name): string
    {
        $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter(fn (string $word): bool => preg_match('/^\p{L}/u', $word) === 1)
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($name, 0, 2));
    }
}
