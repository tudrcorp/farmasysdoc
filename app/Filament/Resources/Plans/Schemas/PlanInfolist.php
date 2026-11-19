<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\Formulary;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;
use App\Models\PlanAgeRange;
use App\Models\PlanSubplan;
use App\Support\Plans\FormularyCoverage;
use App\Support\Plans\PlanAgeRangeRules;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class PlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])
                            ->schema([
                                TextEntry::make('code')->label('Código')->badge()->color('gray'),
                                TextEntry::make('name')->label('Nombre')->weight('medium')->columnSpan(['md' => 2]),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->formatStateUsing(fn (PlanStatus $state): string => $state->label())
                                    ->badge()
                                    ->color(fn (PlanStatus $state): string => $state->color()),
                                TextEntry::make('valid_from')->label('Vigente desde')->date('d/m/Y')->placeholder('—'),
                                TextEntry::make('valid_until')->label('Vigente hasta')->date('d/m/Y')->placeholder('Sin fecha de fin'),
                                TextEntry::make('description')->label('Descripción')->placeholder('—')->columnSpan(['md' => 2]),
                                TextEntry::make('notes')->label('Notas internas')->placeholder('—')->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Tarifa')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        TextEntry::make('premium_mode')
                            ->label('Modalidad')
                            ->formatStateUsing(fn (PlanPremiumMode $state): string => $state->label())
                            ->badge()
                            ->color(fn (PlanPremiumMode $state): string => $state->color())
                            ->helperText(fn (Plan $record): ?string => $record->isRetail() ? $record->premium_mode->description() : null),
                        Grid::make(['default' => 1, 'md' => 2])
                            ->visible(fn (Plan $record): bool => $record->usesFlatPremium())
                            ->schema([
                                TextEntry::make('monthly_premium_usd')->label('Prima (USD)')->money('USD')->placeholder('—'),
                                TextEntry::make('monthly_premium_ves')
                                    ->label('Prima (Bs.)')
                                    ->formatStateUsing(fn (mixed $state): string => 'Bs. '.number_format((float) $state, 2, ',', '.'))
                                    ->placeholder('—'),
                            ]),
                        RepeatableEntry::make('ageRanges')
                            ->label('Por rango de edad')
                            ->visible(fn (Plan $record): bool => $record->usesAgeRanges())
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                TextEntry::make('range')->label('Rango')->state(fn (PlanAgeRange $record): string => $record->label())->weight('medium'),
                                TextEntry::make('monthly_premium_usd')->label('Prima (USD)')->money('USD'),
                                TextEntry::make('monthly_premium_ves')
                                    ->label('Prima (Bs.)')
                                    ->formatStateUsing(fn (mixed $state): string => 'Bs. '.number_format((float) $state, 2, ',', '.')),
                            ])
                            ->placeholder('Sin rangos cargados'),
                        TextEntry::make('age_range_gaps')
                            ->label('Edades sin tarifa')
                            ->state(fn (Plan $record): string => implode(' · ', PlanAgeRangeRules::gaps($record->ageRanges->map->only(['min_age', 'max_age'])->all())))
                            ->icon(Heroicon::ExclamationTriangle)
                            ->color('warning')
                            ->visible(fn (Plan $record): bool => $record->usesAgeRanges()
                                && PlanAgeRangeRules::gaps($record->ageRanges->map->only(['min_age', 'max_age'])->all()) !== []),
                    ])
                    ->columnSpanFull(),

                Section::make('Estructura de costos')
                    ->description('Baremo comercial · tarifa por población.')
                    ->icon(Heroicon::TableCells)
                    ->visible(fn (Plan $record): bool => $record->usesAffiliateBands())
                    ->extraAttributes(['class' => 'fi-client-marketing-behavior'])
                    ->schema([
                        TextEntry::make('medicine_profit_rule')
                            ->label('Precio del medicamento')
                            ->state(fn (Plan $record): string => $record->prices_medicines_by_cost_profit
                                ? 'Porcentaje de ganancia según la cantidad de afiliados'
                                : 'Monto fijo por medicamento')
                            ->icon(Heroicon::Beaker)
                            ->color('info')
                            ->columnSpanFull(),
                        TextEntry::make('affiliate_rate_table')
                            ->hiddenLabel()
                            ->state(fn (Plan $record): string => view('filament.plans.affiliate-rate-table', [
                                'plan' => $record->loadMissing(['subplans', 'affiliateBands.prices']),
                            ])->render())
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Subplanes')
                    ->description('Cada subplan incluye los servicios y catálogos del plan base más los suyos.')
                    ->icon(Heroicon::Squares2x2)
                    ->visible(fn (Plan $record): bool => $record->usesSubplans())
                    ->schema([
                        RepeatableEntry::make('subplans')
                            ->hiddenLabel()
                            ->grid(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->weight('bold')
                                    ->size(TextSize::Large)
                                    ->icon(Heroicon::ShieldCheck)
                                    ->iconColor('primary'),
                                TextEntry::make('description')
                                    ->hiddenLabel()
                                    ->color('gray')
                                    ->visible(fn (PlanSubplan $record): bool => filled($record->description)),
                                TextEntry::make('effective_services')
                                    ->label('Servicios')
                                    ->state(fn (PlanSubplan $record): array => $record->effectiveServices()->pluck('name')->all())
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('Sin servicios'),
                                TextEntry::make('effective_formularies')
                                    ->label('Catálogos')
                                    ->state(fn (PlanSubplan $record): array => $record->effectiveFormularies()->pluck('name')->all())
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('Sin catálogos'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Servicios')
                    ->icon(Heroicon::Squares2x2)
                    ->visible(fn (Plan $record): bool => $record->services()->exists())
                    ->schema([
                        TextEntry::make('services.name')
                            ->hiddenLabel()
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columnSpanFull(),

                Section::make('Compañías aliadas')
                    ->icon(Heroicon::BuildingOffice2)
                    ->description(fn (Plan $record): string => self::partnerSummary($record))
                    ->schema([
                        TextEntry::make('partner_cards')
                            ->hiddenLabel()
                            ->state(fn (Plan $record): string => view('filament.plans.partner-cards', [
                                'cards' => self::partnerCards($record),
                            ])->render())
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Catálogos')
                    ->icon(Heroicon::BookOpen)
                    ->description(fn (Plan $record): string => FormularyCoverage::forFormularies($record->formularies()->pluck('formularies.id')->all()))
                    ->schema([
                        RepeatableEntry::make('formularies')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->weight('medium')
                                    ->icon(Heroicon::BookOpen),
                                TextEntry::make('requirements')
                                    ->hiddenLabel()
                                    ->state(fn (Formulary $record): ?string => $record->requirementsLabel())
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder('Sin requisitos'),
                                TextEntry::make('categories.name')
                                    ->label('Categorías')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('Sin categorías'),
                            ])
                            ->placeholder('—'),
                    ])
                    ->columnSpanFull(),

                Section::make('Registro')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])
                            ->schema([
                                TextEntry::make('creator.name')->label('Creado por')->placeholder('—'),
                                TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y h:i a'),
                                TextEntry::make('updater.name')->label('Actualizado por')->placeholder('—'),
                                TextEntry::make('updated_at')->label('Actualizado')->dateTime('d/m/Y h:i a'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return Collection<int, PartnerCompanyPlan>
     */
    private static function assignments(Plan $plan): Collection
    {
        return $plan->partnerAssignments()
            ->with(['partnerCompany', 'subplan'])
            ->get()
            ->sortBy(fn (PartnerCompanyPlan $assignment): string => mb_strtolower((string) $assignment->partnerCompany?->legal_name))
            ->values();
    }

    private static function partnerSummary(Plan $plan): string
    {
        $assignments = self::assignments($plan);
        $count = $assignments->count();

        if ($count === 0) {
            return 'Aliados que tienen este plan asignado.';
        }

        $summary = $count === 1 ? '1 aliado' : "{$count} aliados";

        if ($plan->isConsumptionFund()) {
            $total = $assignments->sum(fn (PartnerCompanyPlan $assignment): float => (float) $assignment->authorized_amount_usd);

            return "{$summary} · Consumo autorizado total ".self::money($total);
        }

        if ($plan->usesAffiliateBands()) {
            $total = $assignments->sum(fn (PartnerCompanyPlan $assignment): float => (float) $assignment->monthlyTotalUsd());
            $affiliates = $assignments->sum(fn (PartnerCompanyPlan $assignment): int => (int) $assignment->affiliates_count);

            return "{$summary} · ".number_format($affiliates, 0, ',', '.').' afiliados · Total mensual '.self::money($total);
        }

        return $summary;
    }

    /**
     * Datos de las tarjetas de aliados del plan (ya formateados para la vista).
     *
     * @return list<array<string, mixed>>
     */
    private static function partnerCards(Plan $plan): array
    {
        $canViewPartners = PartnerCompanyResource::canViewAny();

        return self::assignments($plan)
            ->filter(fn (PartnerCompanyPlan $assignment): bool => $assignment->partnerCompany !== null)
            ->map(function (PartnerCompanyPlan $assignment) use ($plan, $canViewPartners): array {
                $partner = $assignment->partnerCompany;
                $metrics = [];
                $highlight = null;
                $profits = null;

                if ($plan->isConsumptionFund()) {
                    $highlight = [
                        'label' => 'Consumo autorizado',
                        'value' => $assignment->authorized_amount_usd === null ? 'Sin monto' : self::money($assignment->authorized_amount_usd),
                        'hint' => $assignment->authorized_amount_ves === null ? null : 'Bs. '.number_format((float) $assignment->authorized_amount_ves, 2, ',', '.'),
                    ];

                    $profits = [];

                    if ($assignment->applies_intermediary_profit && $assignment->intermediary_profit_percent !== null) {
                        $profits[] = ['label' => 'Intermediario', 'value' => PartnerCompanyPlan::formatPercent($assignment->intermediary_profit_percent)];
                    }

                    if ($assignment->applies_partner_profit && $assignment->partner_profit_percent !== null) {
                        $profits[] = ['label' => 'Aliado', 'value' => PartnerCompanyPlan::formatPercent($assignment->partner_profit_percent)];
                    }
                } elseif ($plan->usesAffiliateBands()) {
                    $total = $assignment->monthlyTotalUsd();
                    $highlight = ['label' => 'Total mensual', 'value' => $total === null ? '—' : self::money($total), 'hint' => 'USD · pago anticipado'];

                    if ($assignment->subplan !== null) {
                        $metrics[] = ['label' => 'Subplan', 'value' => $assignment->subplan->name];
                    }

                    $metrics[] = [
                        'label' => 'Afiliados',
                        'value' => $assignment->affiliates_count === null ? '—' : number_format($assignment->affiliates_count, 0, ',', '.'),
                        'hint' => filled($assignment->agreed_band_label) ? 'Tramo '.$assignment->agreed_band_label : null,
                    ];
                    $metrics[] = [
                        'label' => 'Tarifa',
                        'value' => $assignment->agreed_rate_usd === null ? '—' : self::money($assignment->agreed_rate_usd),
                        'hint' => 'por afiliado al mes',
                    ];
                }

                return [
                    'name' => $partner->legal_name,
                    'initials' => self::initials((string) $partner->legal_name),
                    'tax_id' => $partner->tax_id,
                    'url' => $canViewPartners ? PartnerCompanyResource::getUrl('view', ['record' => $partner]) : null,
                    'modality' => $partner->payment_modality === null ? null : ['label' => $partner->payment_modality->label(), 'color' => $partner->payment_modality->color()],
                    'is_active' => (bool) $partner->is_active,
                    'highlight' => $highlight,
                    'metrics' => $metrics,
                    'profits' => $profits,
                ];
            })
            ->values()
            ->all();
    }

    private static function initials(string $name): string
    {
        $words = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter(fn (string $word): bool => preg_match('/^\p{L}/u', $word) === 1)
            ->take(2);

        $initials = $words->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->join('');

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($name, 0, 2));
    }

    private static function money(mixed $value): string
    {
        return '$'.number_format((float) $value, 2, ',', '.');
    }
}
