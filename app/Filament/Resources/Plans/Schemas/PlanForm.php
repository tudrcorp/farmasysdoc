<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanPremiumMode;
use App\Enums\PlanStatus;
use App\Models\Formulary;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;
use App\Models\PlanService;
use App\Models\PlanSubplan;
use App\Services\Partners\PlanCostStructureSynchronizer;
use App\Support\Finance\BcvRate;
use App\Support\Plans\FormularyCoverage;
use App\Support\Plans\PlanAffiliateBandRules;
use App\Support\Plans\PlanAgeRangeRules;
use App\Support\Plans\PlanPremiumConverter;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->description('Datos generales y vigencia del plan.')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])
                            ->schema([
                                TextInput::make('code')
                                    ->label('Código')
                                    ->default(fn (): string => Plan::nextCode())
                                    ->required()
                                    ->maxLength(30)
                                    ->unique(ignoreRecord: true)
                                    ->validationMessages(['unique' => 'Ya existe un plan con ese código.'])
                                    ->prefixIcon(Heroicon::Hashtag),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->placeholder('Ej.: Plan Salud Familiar, Plan Corporativo Plus')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['md' => 2]),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options(PlanStatus::options())
                                    ->default(PlanStatus::Draft->value)
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Flag),
                                DatePicker::make('valid_from')
                                    ->label('Vigente desde')
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                                DatePicker::make('valid_until')
                                    ->label('Vigente hasta')
                                    ->helperText('Vacío = sin fecha de fin.')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->afterOrEqual('valid_from')
                                    ->validationMessages(['after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.']),
                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->columnSpan(['md' => 2]),
                                Textarea::make('notes')
                                    ->label('Notas internas')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Tarifa')
                    ->description(fn (): string => self::rateHint())
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Select::make('premium_mode')
                            ->label('Modalidad')
                            ->options(PlanPremiumMode::options())
                            ->default(PlanPremiumMode::Flat->value)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                if (self::modeValue($get) === PlanPremiumMode::AffiliateBands->value && blank($get('affiliate_bands'))) {
                                    $set('affiliate_bands', self::standardBandsState());
                                }
                            })
                            ->helperText(fn (Get $get): ?string => PlanPremiumMode::tryFrom((string) self::modeValue($get))?->description()),
                        Grid::make(['default' => 1, 'md' => 2])
                            ->visible(fn (Get $get): bool => self::modeValue($get) === PlanPremiumMode::Flat->value)
                            ->schema([
                                TextInput::make('monthly_premium_usd')
                                    ->label('Prima mensual (USD)')
                                    ->helperText('Vacío = se define en el convenio con el aliado; 0 = sin costo.')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (mixed $state, Set $set) => $set('monthly_premium_ves', PlanPremiumConverter::usdToVes($state))),
                                TextInput::make('monthly_premium_ves')
                                    ->label('Prima mensual (Bs.)')
                                    ->helperText('Se calcula con la tasa BCV del día; puede ajustarlo.')
                                    ->numeric()
                                    ->prefix('Bs.')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->required(fn (Get $get): bool => self::modeValue($get) === PlanPremiumMode::Flat->value && filled($get('monthly_premium_usd')))
                                    ->validationMessages(['required' => 'Indique la prima en Bs. o deje vacía la prima en USD.']),
                            ]),
                        Repeater::make('ageRanges')
                            ->label('Rangos de edad')
                            ->relationship('ageRanges')
                            ->reorderable(false)
                            ->visible(fn (Get $get): bool => self::modeValue($get) === PlanPremiumMode::AgeRanges->value)
                            ->required(fn (Get $get): bool => self::modeValue($get) === PlanPremiumMode::AgeRanges->value)
                            ->minItems(1)
                            ->addActionLabel('Agregar rango')
                            ->columns(['default' => 1, 'md' => 4])
                            ->itemLabel(fn (array $state): ?string => self::rangeItemLabel($state))
                            ->collapsible()
                            ->live()
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $error = PlanAgeRangeRules::firstError(is_array($value) ? $value : []);
                                    if ($error !== null) {
                                        $fail($error);
                                    }
                                },
                            ])
                            ->schema([
                                TextInput::make('min_age')
                                    ->label('Edad desde')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(PlanAgeRangeRules::MAX_AGE)
                                    ->suffix('años')
                                    ->required()
                                    ->live(onBlur: true),
                                TextInput::make('max_age')
                                    ->label('Edad hasta')
                                    ->helperText('Vacío = en adelante.')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(PlanAgeRangeRules::MAX_AGE)
                                    ->suffix('años')
                                    ->live(onBlur: true),
                                TextInput::make('monthly_premium_usd')
                                    ->label('Prima mensual (USD)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (mixed $state, Set $set) => $set('monthly_premium_ves', PlanPremiumConverter::usdToVes($state))),
                                TextInput::make('monthly_premium_ves')
                                    ->label('Prima mensual (Bs.)')
                                    ->numeric()
                                    ->prefix('Bs.')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->required(),
                            ]),
                        TextEntry::make('age_range_gaps')
                            ->label('Edades sin tarifa')
                            ->state(fn (Get $get): string => self::gapsSummary($get('ageRanges')))
                            ->icon(Heroicon::ExclamationTriangle)
                            ->color('warning')
                            ->visible(fn (Get $get): bool => self::modeValue($get) === PlanPremiumMode::AgeRanges->value && PlanAgeRangeRules::gaps(self::rangeItems($get('ageRanges'))) !== []),
                        Toggle::make('has_subplans')
                            ->label('Este plan maneja subplanes')
                            ->helperText('Ej.: Cápita con «Plan Agudos» y «Plan Integral». Cada subplan tiene su columna de precios y puede agregar servicios y catálogos a los del plan base.')
                            ->default(false)
                            ->live()
                            ->inline(false)
                            ->visible(fn (Get $get): bool => self::usesAffiliateBands($get)),
                        Repeater::make('subplans')
                            ->label('Subplanes')
                            ->visible(fn (Get $get): bool => self::usesAffiliateBands($get) && (bool) $get('has_subplans'))
                            ->required(fn (Get $get): bool => self::usesAffiliateBands($get) && (bool) $get('has_subplans'))
                            ->minItems(1)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar subplan')
                            ->reorderable()
                            ->collapsible()
                            ->live()
                            ->columns(['default' => 1, 'md' => 2])
                            ->itemLabel(fn (array $state): string => filled($state['name'] ?? null) ? (string) $state['name'] : 'Nuevo subplan')
                            ->validationMessages(['min' => 'Agregue al menos un subplan o desactive «Este plan maneja subplanes».'])
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $names = collect(is_array($value) ? $value : [])
                                        ->map(fn (mixed $item): string => mb_strtolower(trim((string) (is_array($item) ? ($item['name'] ?? '') : ''))))
                                        ->filter();

                                    if ($names->count() !== $names->unique()->count()) {
                                        $fail('Hay subplanes con el mismo nombre.');
                                    }
                                },
                            ])
                            ->schema([
                                Hidden::make('key')
                                    ->default(fn (): string => (string) Str::uuid()),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->placeholder('Ej.: Plan Agudos, Plan Integral')
                                    ->required()
                                    ->validationMessages(['required' => 'Indique el nombre del subplan.'])
                                    ->maxLength(255)
                                    ->live(onBlur: true),
                                TextInput::make('description')
                                    ->label('Descripción')
                                    ->maxLength(500),
                                Select::make('services')
                                    ->label('Servicios adicionales')
                                    ->helperText('Además de los servicios del plan base.')
                                    ->options(fn (): array => self::activeServiceOptions())
                                    ->multiple()
                                    ->searchable(),
                                Select::make('formularies')
                                    ->label('Catálogos adicionales')
                                    ->helperText('Además de los catálogos del plan base.')
                                    ->options(fn (): array => self::activeFormularyOptions())
                                    ->multiple()
                                    ->searchable(),
                            ]),
                        Repeater::make('affiliate_bands')
                            ->label('Baremo · USD por afiliado al mes')
                            ->visible(fn (Get $get): bool => self::usesAffiliateBands($get))
                            ->required(fn (Get $get): bool => self::usesAffiliateBands($get))
                            ->minItems(1)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar tramo')
                            ->reorderable(false)
                            ->live()
                            ->hintAction(
                                Action::make('loadStandardBands')
                                    ->label('Cargar tramos estándar')
                                    ->icon(Heroicon::ArrowPath)
                                    ->requiresConfirmation(fn (Get $get): bool => filled($get('affiliate_bands')))
                                    ->modalHeading('Cargar tramos estándar')
                                    ->modalDescription('Reemplaza los tramos actuales por los 6 tramos del baremo comercial (500 - 999 hasta 100.000 o más). Los precios quedan vacíos.')
                                    ->action(fn (Set $set) => $set('affiliate_bands', self::standardBandsState())),
                            )
                            ->table(fn (Get $get): array => [
                                TableColumn::make('Afiliados desde')->markAsRequired(),
                                TableColumn::make('Hasta (vacío = o más)'),
                                ...collect(self::priceColumns($get))
                                    ->map(fn (string $label): TableColumn => TableColumn::make($label)->markAsRequired())
                                    ->values()
                                    ->all(),
                            ])
                            ->schema(fn (Get $get): array => [
                                TextInput::make('min_affiliates')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->validationMessages(['required' => 'Indique desde cuántos afiliados aplica el tramo.'])
                                    ->live(onBlur: true),
                                TextInput::make('max_affiliates')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->placeholder('o más')
                                    ->live(onBlur: true),
                                ...collect(self::priceColumns($get))
                                    ->map(fn (string $label, string $key): TextInput => TextInput::make("prices.{$key}")
                                        ->numeric()
                                        ->prefix('$')
                                        ->minValue(0.01)
                                        ->step(0.01)
                                        ->required()
                                        ->validationMessages(['required' => "Falta el precio de {$label} en este tramo."])
                                        ->validationAttribute("precio de {$label}")
                                        ->live(onBlur: true))
                                    ->values()
                                    ->all(),
                            ])
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    $error = PlanAffiliateBandRules::firstError(is_array($value) ? array_values(array_filter($value, 'is_array')) : []);
                                    if ($error !== null) {
                                        $fail($error);
                                    }
                                },
                            ]),
                        TextEntry::make('affiliate_band_warnings')
                            ->label('Revise el baremo')
                            ->state(fn (Get $get): string => implode(' ', self::bandWarnings($get)))
                            ->icon(Heroicon::ExclamationTriangle)
                            ->color('warning')
                            ->visible(fn (Get $get): bool => self::usesAffiliateBands($get) && self::bandWarnings($get) !== []),
                    ])
                    ->columnSpanFull(),

                Section::make('Servicios')
                    ->description(fn (Get $get): string => self::usesAffiliateBands($get) && (bool) $get('has_subplans')
                        ? 'Opcional. Servicios del plan base: aplican a todos los subplanes (cada subplan puede agregar los suyos en «Tarifa»).'
                        : 'Opcional. Servicios del maestro incluidos en el plan; un plan puede no tener servicios.')
                    ->icon(Heroicon::Squares2x2)
                    ->schema([
                        Select::make('services')
                            ->label('Servicios')
                            ->relationship(
                                'services',
                                'name',
                                fn (Builder $query, ?Plan $record): Builder => $query
                                    ->where(fn (Builder $inner): Builder => $inner
                                        ->where('is_active', true)
                                        ->when($record !== null, fn (Builder $q): Builder => $q->orWhereHas('plans', fn (Builder $p): Builder => $p->whereKey($record->getKey()))))
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (PlanService $record): string => "{$record->code} · {$record->name}")
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columnSpanFull(),

                Section::make('Catálogos')
                    ->description(fn (Get $get): string => self::usesAffiliateBands($get) && (bool) $get('has_subplans')
                        ? 'Opcional. Catálogos del plan base: aplican a todos los subplanes (cada subplan puede agregar los suyos en «Tarifa»).'
                        : 'Opcional. Catálogos de principios activos que cubre el plan; un catálogo puede estar en varios planes.')
                    ->icon(Heroicon::BookOpen)
                    ->schema([
                        Select::make('formularies')
                            ->label('Catálogos')
                            ->relationship(
                                'formularies',
                                'name',
                                fn (Builder $query, ?Plan $record): Builder => $query
                                    ->where(fn (Builder $inner): Builder => $inner
                                        ->where('is_active', true)
                                        ->when($record !== null, fn (Builder $q): Builder => $q->orWhereHas('plans', fn (Builder $p): Builder => $p->whereKey($record->getKey()))))
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Formulary $record): string => "{$record->code} · {$record->name}")
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live(),
                        TextEntry::make('formulary_coverage')
                            ->label('Cobertura')
                            ->state(fn (Get $get): string => FormularyCoverage::forFormularies((array) ($get('formularies') ?? [])))
                            ->icon(Heroicon::Beaker)
                            ->color('info'),
                    ])
                    ->columnSpanFull(),

                Section::make('Compañías aliadas')
                    ->description(fn (Get $get): string => self::modeValue($get) === PlanPremiumMode::ConsumptionFund->value
                        ? 'Fondo de consumo: indique a cada aliado su monto de consumo autorizado y, si la negociación lo usa, los porcentajes de ganancia.'
                        : 'Aliados que tienen este plan. Un plan puede asignarse a uno o varios aliados.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Repeater::make('partnerAssignments')
                            ->hiddenLabel()
                            ->relationship('partnerAssignments')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Asignar aliado')
                            ->columns(['default' => 1, 'md' => 6])
                            ->itemLabel(fn (array $state): ?string => self::assignmentItemLabel($state))
                            ->collapsible()
                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => [
                                ...$data,
                                'subplan_key' => filled($data['plan_subplan_id'] ?? null) ? PlanSubplan::query()->whereKey($data['plan_subplan_id'])->value('uuid') : null,
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Get $get): array => [
                                ...self::assignmentContractData($data, $get),
                                'created_by' => auth()->id(),
                                'updated_by' => auth()->id(),
                            ])
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data, Get $get): array => [
                                ...self::assignmentContractData($data, $get),
                                'updated_by' => auth()->id(),
                            ])
                            ->schema([
                                Select::make('partner_company_id')
                                    ->label('Aliado')
                                    ->options(fn (): array => PartnerCompany::query()
                                        ->orderBy('legal_name')
                                        ->get(['id', 'legal_name', 'trade_name'])
                                        ->mapWithKeys(fn (PartnerCompany $company): array => [$company->getKey() => self::partnerLabel($company)])
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->validationMessages(['distinct' => 'Este aliado ya está asignado al plan.'])
                                    ->live()
                                    ->columnSpan(['md' => 6]),
                                Grid::make(['default' => 1, 'md' => 3])
                                    ->visible(fn (Get $get): bool => self::usesAffiliateBands($get, '../../'))
                                    ->columnSpan(['md' => 6])
                                    ->schema([
                                        Select::make('subplan_key')
                                            ->label('Subplan')
                                            ->options(fn (Get $get): array => self::subplanOptions($get, '../../'))
                                            ->visible(fn (Get $get): bool => (bool) $get('../../has_subplans'))
                                            ->required(fn (Get $get): bool => self::usesAffiliateBands($get, '../../') && (bool) $get('../../has_subplans'))
                                            ->validationMessages(['required' => 'Elija el subplan contratado.'])
                                            ->native(false)
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestAgreedRate($get, $set)),
                                        TextInput::make('affiliates_count')
                                            ->label('Afiliados contratados')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->required(fn (Get $get): bool => self::usesAffiliateBands($get, '../../'))
                                            ->validationMessages(['required' => 'Indique la cantidad de afiliados contratados.'])
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::suggestAgreedRate($get, $set))
                                            ->rules([
                                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                    if (! self::usesAffiliateBands($get, '../../') || ! is_numeric($value)) {
                                                        return;
                                                    }

                                                    $bands = self::bandItems($get('../../affiliate_bands'));
                                                    if (PlanAffiliateBandRules::bandForCount($bands, (int) $value) === null) {
                                                        $minimum = PlanAffiliateBandRules::minimumAffiliates($bands);
                                                        $fail($minimum !== null && (int) $value < $minimum
                                                            ? 'El mínimo para contratar este plan es de '.number_format($minimum, 0, ',', '.').' afiliados.'
                                                            : 'Ningún tramo del baremo cubre esa cantidad de afiliados.');
                                                    }
                                                },
                                            ]),
                                        TextInput::make('agreed_rate_usd')
                                            ->label('Tarifa acordada (USD por afiliado)')
                                            ->helperText('Se sugiere con el baremo; puede ajustarla si se negoció otra.')
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required(fn (Get $get): bool => self::usesAffiliateBands($get, '../../'))
                                            ->validationMessages(['required' => 'Indique la tarifa acordada por afiliado.'])
                                            ->live(onBlur: true),
                                        TextEntry::make('contract_summary')
                                            ->hiddenLabel()
                                            ->state(fn (Get $get): ?string => self::contractSummary($get))
                                            ->icon(Heroicon::Calculator)
                                            ->color('info')
                                            ->visible(fn (Get $get): bool => self::contractSummary($get) !== null)
                                            ->columnSpanFull(),
                                    ]),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->visible(fn (Get $get): bool => self::modeValue($get, '../../') === PlanPremiumMode::ConsumptionFund->value)
                                    ->columnSpan(['md' => 2])
                                    ->schema([
                                        TextInput::make('authorized_amount_usd')
                                            ->label('Consumo autorizado (USD)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required(fn (Get $get): bool => self::modeValue($get, '../../') === PlanPremiumMode::ConsumptionFund->value)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (mixed $state, Set $set) => $set('authorized_amount_ves', PlanPremiumConverter::usdToVes($state))),
                                        TextInput::make('authorized_amount_ves')
                                            ->label('Consumo autorizado (Bs.)')
                                            ->helperText('Sugerido con la tasa BCV de hoy; puede ajustarlo.')
                                            ->numeric()
                                            ->prefix('Bs.')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->required(fn (Get $get): bool => self::modeValue($get, '../../') === PlanPremiumMode::ConsumptionFund->value),
                                    ]),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->visible(fn (Get $get): bool => self::modeValue($get, '../../') === PlanPremiumMode::ConsumptionFund->value)
                                    ->columnSpan(['md' => 2])
                                    ->schema([
                                        Toggle::make('applies_intermediary_profit')
                                            ->label('Aplica % del intermediario')
                                            ->default(false)
                                            ->live()
                                            ->inline(false),
                                        TextInput::make('intermediary_profit_percent')
                                            ->label('Ganancia del intermediario')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step('any')
                                            ->visible(fn (Get $get): bool => (bool) $get('applies_intermediary_profit'))
                                            ->required(fn (Get $get): bool => (bool) $get('applies_intermediary_profit')),
                                    ]),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->visible(fn (Get $get): bool => self::modeValue($get, '../../') === PlanPremiumMode::ConsumptionFund->value)
                                    ->columnSpan(['md' => 2])
                                    ->schema([
                                        Toggle::make('applies_partner_profit')
                                            ->label('Aplica % del aliado')
                                            ->default(false)
                                            ->live()
                                            ->inline(false),
                                        TextInput::make('partner_profit_percent')
                                            ->label('Ganancia del aliado')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step('any')
                                            ->visible(fn (Get $get): bool => (bool) $get('applies_partner_profit'))
                                            ->required(fn (Get $get): bool => (bool) $get('applies_partner_profit')),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function modeValue(Get $get, string $path = ''): ?string
    {
        $mode = $get($path.'premium_mode');

        return $mode instanceof PlanPremiumMode ? $mode->value : (is_string($mode) ? $mode : null);
    }

    private static function usesAffiliateBands(Get $get, string $path = ''): bool
    {
        return self::modeValue($get, $path) === PlanPremiumMode::AffiliateBands->value;
    }

    /**
     * @return list<array{min_affiliates: int, max_affiliates: int|null, prices: array<string, mixed>}>
     */
    private static function standardBandsState(): array
    {
        return array_map(fn (array $band): array => [...$band, 'prices' => []], PlanAffiliateBandRules::STANDARD_BANDS);
    }

    /**
     * Columnas de precio del baremo: una por subplan (clave = uuid) o una sola si no hay subplanes.
     *
     * @return array<string, string>
     */
    private static function priceColumns(Get $get, string $path = ''): array
    {
        if (! (bool) $get($path.'has_subplans')) {
            return [PlanCostStructureSynchronizer::BASE_PRICE_KEY => 'Tarifa (USD)'];
        }

        $columns = self::subplanOptions($get, $path);

        return $columns === [] ? [PlanCostStructureSynchronizer::BASE_PRICE_KEY => 'Tarifa (USD)'] : $columns;
    }

    /**
     * @return array<string, string> uuid => nombre
     */
    private static function subplanOptions(Get $get, string $path = ''): array
    {
        $options = [];
        $index = 1;

        foreach ((array) ($get($path.'subplans') ?? []) as $item) {
            if (! is_array($item) || blank($item['key'] ?? null)) {
                continue;
            }

            $options[(string) $item['key']] = filled($item['name'] ?? null) ? (string) $item['name'] : "Subplan {$index}";
            $index++;
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function bandItems(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
    }

    /**
     * @return list<string>
     */
    private static function bandWarnings(Get $get): array
    {
        return PlanAffiliateBandRules::priceWarnings(self::bandItems($get('affiliate_bands')), self::priceColumns($get));
    }

    /**
     * Tramo y tarifa del baremo para la asignación (desde el estado del formulario).
     *
     * @return array{label: string, rate: float|null}|null
     */
    private static function assignmentBand(Get $get): ?array
    {
        $count = $get('affiliates_count');

        if (! is_numeric($count) || ! self::usesAffiliateBands($get, '../../')) {
            return null;
        }

        $band = PlanAffiliateBandRules::bandForCount(self::bandItems($get('../../affiliate_bands')), (int) $count);

        if ($band === null) {
            return null;
        }

        $priceKey = (bool) $get('../../has_subplans') ? (string) $get('subplan_key') : PlanCostStructureSynchronizer::BASE_PRICE_KEY;
        $rate = $band['prices'][$priceKey] ?? null;

        return [
            'label' => PlanAffiliateBandRules::describe((int) $band['min_affiliates'], is_numeric($band['max_affiliates'] ?? null) ? (int) $band['max_affiliates'] : null),
            'rate' => is_numeric($rate) ? (float) $rate : null,
        ];
    }

    private static function suggestAgreedRate(Get $get, Set $set): void
    {
        $band = self::assignmentBand($get);

        if ($band !== null && $band['rate'] !== null) {
            $set('agreed_rate_usd', number_format($band['rate'], 2, '.', ''));
        }
    }

    private static function contractSummary(Get $get): ?string
    {
        $count = $get('affiliates_count');
        $rate = $get('agreed_rate_usd');
        $band = self::assignmentBand($get);

        if (! is_numeric($count) || ! is_numeric($rate)) {
            return $band === null ? null : 'Tramo '.$band['label'];
        }

        $total = round((int) $count * (float) $rate, 2);
        $text = number_format((int) $count, 0, ',', '.').' afiliados × $'.number_format((float) $rate, 2, ',', '.')
            .' = $'.number_format($total, 2, ',', '.').' al mes';

        if ($band !== null) {
            $text = 'Tramo '.$band['label'].' · '.$text;

            if ($band['rate'] !== null && round($band['rate'], 2) !== round((float) $rate, 2)) {
                $text .= ' (baremo: $'.number_format($band['rate'], 2, ',', '.').')';
            }
        }

        return $text;
    }

    /**
     * Normaliza los datos de contratación por afiliados antes de guardar la asignación.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function assignmentContractData(array $data, Get $get): array
    {
        $subplanKey = $data['subplan_key'] ?? null;
        unset($data['subplan_key']);

        if (! self::usesAffiliateBands($get)) {
            return [...$data, 'plan_subplan_id' => null, 'affiliates_count' => null, 'agreed_rate_usd' => null, 'agreed_band_label' => null];
        }

        $data['plan_subplan_id'] = (bool) $get('has_subplans') && filled($subplanKey)
            ? PlanSubplan::query()->where('uuid', $subplanKey)->value('id')
            : null;

        $band = is_numeric($data['affiliates_count'] ?? null)
            ? PlanAffiliateBandRules::bandForCount(self::bandItems($get('affiliate_bands')), (int) $data['affiliates_count'])
            : null;

        $data['agreed_band_label'] = $band === null
            ? null
            : PlanAffiliateBandRules::describe((int) $band['min_affiliates'], is_numeric($band['max_affiliates'] ?? null) ? (int) $band['max_affiliates'] : null);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private static function activeServiceOptions(): array
    {
        return PlanService::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (PlanService $service): array => [(string) $service->getKey() => "{$service->code} · {$service->name}"])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function activeFormularyOptions(): array
    {
        return Formulary::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Formulary $formulary): array => [(string) $formulary->getKey() => "{$formulary->code} · {$formulary->name}"])
            ->all();
    }

    private static function partnerLabel(PartnerCompany $company): string
    {
        return filled($company->trade_name) && $company->trade_name !== $company->legal_name
            ? "{$company->legal_name} ({$company->trade_name})"
            : (string) $company->legal_name;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function assignmentItemLabel(array $state): string
    {
        $company = is_numeric($state['partner_company_id'] ?? null)
            ? PartnerCompany::query()->find((int) $state['partner_company_id'])
            : null;

        $label = $company === null ? 'Nuevo aliado' : self::partnerLabel($company);

        if (is_numeric($state['authorized_amount_usd'] ?? null)) {
            $label .= ' · $'.number_format((float) $state['authorized_amount_usd'], 2, ',', '.');
        }

        $percents = [];
        if (($state['applies_intermediary_profit'] ?? false) && is_numeric($state['intermediary_profit_percent'] ?? null)) {
            $percents[] = 'Interm. '.PartnerCompanyPlan::formatPercent($state['intermediary_profit_percent']);
        }
        if (($state['applies_partner_profit'] ?? false) && is_numeric($state['partner_profit_percent'] ?? null)) {
            $percents[] = 'Aliado '.PartnerCompanyPlan::formatPercent($state['partner_profit_percent']);
        }

        return $percents === [] ? $label : $label.' · '.implode(' · ', $percents);
    }

    private static function rateHint(): string
    {
        $rate = PlanPremiumConverter::todayRate();

        return $rate === null
            ? 'Prima mensual por persona. Indíquela en USD y en Bs. (no se pudo obtener la tasa BCV de hoy; cargue los Bs. manualmente).'
            : 'Prima mensual por persona. Indíquela en USD; los Bs. se sugieren con la tasa BCV de hoy (Bs. '.BcvRate::format($rate).' por USD).';
    }

    /**
     * @return list<array{min_age: mixed, max_age: mixed}>
     */
    private static function rangeItems(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
    }

    private static function gapsSummary(mixed $items): string
    {
        $gaps = PlanAgeRangeRules::gaps(self::rangeItems($items));

        return $gaps === [] ? '—' : implode(' · ', $gaps);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function rangeItemLabel(array $state): ?string
    {
        if (! is_numeric($state['min_age'] ?? null)) {
            return 'Nuevo rango';
        }

        $label = is_numeric($state['max_age'] ?? null)
            ? "{$state['min_age']} a {$state['max_age']} años"
            : "{$state['min_age']} años en adelante";

        return is_numeric($state['monthly_premium_usd'] ?? null)
            ? $label.' · $'.number_format((float) $state['monthly_premium_usd'], 2, ',', '.')
            : $label;
    }
}
