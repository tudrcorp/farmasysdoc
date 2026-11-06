<?php

namespace App\Filament\Resources\PartnerAgreements\Schemas;

use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Filament\Resources\PartnerAgreements\Support\PartnerAgreementFormDefaults;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Services\Partners\PartnerAgreementRules;
use App\Support\Filament\BranchAuthScope;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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

class PartnerCompanyAgreementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Convenio')
                    ->description('Aliado y tipo de versión. Renovaciones, modificaciones y renegociaciones parten del convenio vigente.')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('partner_company_id')
                                    ->label('Compañía aliada')
                                    ->options(fn (): array => BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())
                                        ->orderBy('legal_name')
                                        ->pluck('legal_name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->disabledOn('edit')
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::applyDefaults($get, $set))
                                    ->prefixIcon(Heroicon::BuildingOffice2),
                                Select::make('type')
                                    ->label('Tipo')
                                    ->options(PartnerAgreementType::options())
                                    ->default(PartnerAgreementType::Initial->value)
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->disabledOn('edit')
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::applyDefaults($get, $set))
                                    ->prefixIcon(Heroicon::Tag),
                                TextEntry::make('previous_agreement_summary')
                                    ->label('Convenio que se renueva / modifica')
                                    ->state(fn (Get $get): string => self::previousSummary($get))
                                    ->visible(fn (Get $get): bool => self::type($get)?->requiresPreviousAgreement() ?? false)
                                    ->columnSpanFull(),
                                Textarea::make('change_reason')
                                    ->label('Motivo')
                                    ->placeholder('Qué cambia y por qué (renovación, ajuste de límite, nuevo descuento…)')
                                    ->rows(2)
                                    ->required(fn (Get $get): bool => self::type($get)?->requiresPreviousAgreement() ?? false)
                                    ->visible(fn (Get $get): bool => self::type($get)?->requiresPreviousAgreement() ?? false)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Vigencia')
                    ->description('El convenio vence al final del día de la fecha de fin. Se avisa a los involucrados antes del vencimiento.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                DatePicker::make('starts_on')
                                    ->label('Inicio')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->live(onBlur: true),
                                Select::make('term_preset')
                                    ->label('Plazo rápido')
                                    ->options([3 => '3 meses', 6 => '6 meses', 12 => '1 año', 24 => '2 años', 36 => '3 años'])
                                    ->placeholder('Personalizado')
                                    ->native(false)
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                        if (blank($state) || blank($get('starts_on'))) {
                                            return;
                                        }

                                        $set('ends_on', CarbonImmutable::parse((string) $get('starts_on'))
                                            ->addMonthsNoOverflow((int) $state)
                                            ->subDay()
                                            ->toDateString());
                                    }),
                                DatePicker::make('ends_on')
                                    ->label('Fin')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->afterOrEqual('starts_on')
                                    ->validationMessages(['after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.']),
                                Toggle::make('auto_renews')
                                    ->label('Renovación automática')
                                    ->helperText('Al vencer se renueva solo, con las mismas condiciones y el mismo contrato firmado.')
                                    ->live()
                                    ->inline(false),
                                TextInput::make('renewal_term_months')
                                    ->label('Plazo de cada renovación (meses)')
                                    ->helperText('Vacío = mismo plazo que este convenio.')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(120)
                                    ->visible(fn (Get $get): bool => (bool) $get('auto_renews')),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Condiciones negociadas')
                    ->description('Al entrar en vigencia se copian a la ficha del aliado. Si cambia el límite, el saldo disponible se mueve por la diferencia.')
                    ->icon(Heroicon::Scale)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'lg' => 4])
                            ->schema([
                                Select::make('payment_modality')
                                    ->label('Modalidad de pago')
                                    ->options(PartnerCompanyPaymentModality::options())
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->prefixIcon(Heroicon::CreditCard),
                                TextInput::make('credit_limit_usd')
                                    ->label('Límite de crédito (USD)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->visible(fn (Get $get): bool => self::usesCreditLimit($get))
                                    ->required(fn (Get $get): bool => self::usesCreditLimit($get)),
                                TextInput::make('discount_percentage')
                                    ->label('Descuento %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step('any'),
                                TextInput::make('profit_percentage_a')
                                    ->label('Ganancia % A')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step('any'),
                                Textarea::make('terms')
                                    ->label('Términos del convenio')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contrato firmado')
                    ->description('Obligatorio para enviar a aprobación. PDF o imagen.')
                    ->icon(Heroicon::PaperClip)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                FileUpload::make('signed_document_path')
                                    ->label('Archivo del contrato')
                                    ->disk(fn (): string => PartnerCompanyAgreement::documentDisk())
                                    ->directory(fn (): string => (string) config('partner_agreements.document_directory'))
                                    ->visibility('private')
                                    ->acceptedFileTypes(fn (): array => (array) config('partner_agreements.document_accepted_mime_types'))
                                    ->maxSize(fn (): int => (int) config('partner_agreements.document_max_size_kb'))
                                    ->storeFileNamesIn('signed_document_name')
                                    ->openable()
                                    ->downloadable()
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                DatePicker::make('signed_on')
                                    ->label('Fecha de firma')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->maxDate(now()),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function applyDefaults(Get $get, Set $set): void
    {
        $partnerId = filled($get('partner_company_id')) ? (int) $get('partner_company_id') : null;

        foreach (PartnerAgreementFormDefaults::for($partnerId, self::type($get)) as $key => $value) {
            $set($key, $value);
        }
    }

    private static function previousSummary(Get $get): string
    {
        $partner = filled($get('partner_company_id')) ? PartnerCompany::query()->find((int) $get('partner_company_id')) : null;
        $type = self::type($get);

        if (! $partner instanceof PartnerCompany || $type === null) {
            return 'Seleccione el aliado.';
        }

        $previous = app(PartnerAgreementRules::class)->resolvePreviousAgreement($partner, $type);

        if ($previous === null) {
            return $type === PartnerAgreementType::Renewal
                ? 'El aliado no tiene convenio vigente ni vencido: registre un convenio inicial.'
                : 'El aliado no tiene convenio vigente.';
        }

        return sprintf('%s · %s · %s', $previous->number, $previous->status->label(), $previous->termLabel());
    }

    private static function type(Get $get): ?PartnerAgreementType
    {
        $state = $get('type');

        return $state instanceof PartnerAgreementType ? $state : PartnerAgreementType::tryFrom((string) $state);
    }

    private static function usesCreditLimit(Get $get): bool
    {
        $state = $get('payment_modality');
        $modality = $state instanceof PartnerCompanyPaymentModality ? $state : PartnerCompanyPaymentModality::tryFrom((string) $state);

        return $modality?->usesCreditLimit() ?? false;
    }
}
