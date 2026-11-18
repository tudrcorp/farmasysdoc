<?php

namespace App\Filament\Resources\PartnerCompanies\Schemas;

use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyDocumentType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Enums\PartnerPanelPermission;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyDocument;
use App\Models\State;
use App\Models\User;
use App\Services\Partners\PartnerPanelUserService;
use App\Support\PartnerCompanyAlliedUsersFormSync;
use App\Support\Partners\AgreementTermStatus;
use App\Support\Partners\PartnerPanel;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class PartnerCompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación de la compañía')
                    ->description('Define los datos legales y de identificación para facturación y trazabilidad.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('code')
                                    ->label('Código interno')
                                    ->disabled()
                                    ->placeholder('Se genera al guardar')
                                    ->helperText('Se completa automáticamente al crear la compañía aliada.')
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->dehydrated(false),
                                TextInput::make('legal_name')
                                    ->label('Razón social')
                                    ->placeholder('Nombre legal registrado')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentText)
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextInput::make('trade_name')
                                    ->label('Nombre comercial')
                                    ->placeholder('Nombre usado en operación')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                TextInput::make('tax_id')
                                    ->label('RIF')
                                    ->placeholder('Ej. J123456789')
                                    ->helperText('Solo letras mayúsculas y números, sin espacios ni caracteres especiales.')
                                    ->required()
                                    ->rule('regex:/^[A-Z0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El RIF solo puede contener letras mayúsculas (A-Z) y números, sin guiones, espacios ni símbolos.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? strtoupper(str_replace(' ', '', $state))
                                        : null)
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Identification),
                                Select::make('client_type')
                                    ->label('Tipo de cliente')
                                    ->options(PartnerCompanyClientType::options())
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Tag),
                                Select::make('branches')
                                    ->label('Sucursales')
                                    ->helperText('Sucursales donde aplica el convenio con el aliado.')
                                    ->relationship('branches', 'name', fn ($query) => $query->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn (Branch $record): string => (string) $record->name)
                                    ->multiple()
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                Toggle::make('is_active')
                                    ->label('Compañía activa')
                                    ->default(true)
                                    ->required()
                                    ->visible(function (): bool {
                                        $authUser = request()->user();

                                        if (! $authUser instanceof User) {
                                            return false;
                                        }

                                        return ! $authUser->isManager();
                                    })
                                    ->disabled(function (): bool {
                                        $authUser = request()->user();

                                        return $authUser instanceof User
                                            && $authUser->isManager()
                                            && ! $authUser->isAdministrator();
                                    })
                                    ->helperText(function (): string {
                                        $authUser = request()->user();

                                        return $authUser instanceof User
                                            && $authUser->isManager()
                                            && ! $authUser->isAdministrator()
                                            ? 'Creada por Gerente: la activación la realiza un Administrador.'
                                            : 'Desactiva este estado para ocultar la compañía de nuevas operaciones.';
                                    })
                                    ->inline(false),
                                Toggle::make('can_place_orders')
                                    ->label('Puede hacer pedidos del inventario')
                                    ->helperText('Habilita el inventario de Las Delicias en el panel del aliado. El administrador de esa empresa podrá asignar el permiso a sus analistas.')
                                    ->default(false)
                                    ->live()
                                    ->inline(false)
                                    ->visible(fn (): bool => request()->user() instanceof User && request()->user()->isAdministrator())
                                    ->dehydrated(fn (): bool => request()->user() instanceof User && request()->user()->isAdministrator()),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto corporativo')
                    ->description('Canales institucionales para comunicación operativa.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('email')
                                    ->label('Correo corporativo')
                                    ->email()
                                    ->placeholder('contacto@empresa.com')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Envelope)
                                    ->autocomplete('email'),
                                TextInput::make('website')
                                    ->label('Sitio web')
                                    ->url()
                                    ->placeholder('https://')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::GlobeAlt)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === '' || $state === null ? null : $state),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono fijo')
                                    ->tel()
                                    ->placeholder('Solo números')
                                    ->helperText('Solo números, sin espacios ni caracteres especiales.')
                                    ->rule('regex:/^[0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El teléfono fijo solo puede contener números, sin espacios ni caracteres especiales.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? preg_replace('/[^0-9]/', '', $state)
                                        : null)
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::Phone),
                                TextInput::make('mobile_phone')
                                    ->label('Celular / WhatsApp')
                                    ->tel()
                                    ->placeholder('Solo números')
                                    ->helperText('Solo números, sin espacios ni caracteres especiales.')
                                    ->rule('regex:/^[0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'El celular solo puede contener números, sin espacios ni caracteres especiales.',
                                    ])
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                        ? preg_replace('/[^0-9]/', '', $state)
                                        : null)
                                    ->maxLength(40)
                                    ->prefixIcon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->description('Dirección operativa con selects anidados por país, estado y ciudad.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextInput::make('address')
                            ->label('Dirección')
                            ->placeholder('Calle, número, barrio, referencia')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                Select::make('country')
                                    ->label('País')
                                    ->required()
                                    ->default('VENEZUELA')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->options(fn (): array => Country::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'name')
                                        ->all())
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('state', null);
                                        $set('city', null);
                                    })
                                    ->prefixIcon(Heroicon::GlobeAlt),
                                Select::make('state')
                                    ->label('Estado')
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('country')))
                                    ->options(function (Get $get): array {
                                        $countryName = $get('country');

                                        if (blank($countryName)) {
                                            return [];
                                        }

                                        return State::query()
                                            ->whereHas('country', fn ($query) => $query->where('name', $countryName))
                                            ->orderBy('name')
                                            ->pluck('name', 'name')
                                            ->all();
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('city', null);
                                    })
                                    ->prefixIcon(Heroicon::Map),
                                Select::make('city')
                                    ->label('Ciudad')
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => blank($get('state')))
                                    ->options(function (Get $get): array {
                                        $stateName = $get('state');
                                        $countryName = $get('country');

                                        if (blank($stateName) || blank($countryName)) {
                                            return [];
                                        }

                                        return City::query()
                                            ->whereHas('country', fn ($query) => $query->where('name', $countryName))
                                            ->whereHas('state', fn ($query) => $query->where('name', $stateName))
                                            ->orderBy('name')
                                            ->pluck('name', 'name')
                                            ->all();
                                    })
                                    ->prefixIcon(Heroicon::BuildingLibrary),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Acuerdo de negociación')
                    ->description(fn (?PartnerCompany $record): string => self::isGovernedByAgreements($record)
                        ? 'Este aliado opera con convenios: modalidad, límite, descuento y términos se cambian desde «Convenios y renovaciones». El saldo disponible sí se ajusta aquí (abonos).'
                        : 'Modalidad de pago, límite de crédito y condiciones pactadas con el aliado.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('payment_modality')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Modalidad de pago')
                                    ->helperText('Crédito e integral requieren límite de crédito aprobado.')
                                    ->options(PartnerCompanyPaymentModality::options())
                                    ->required(fn (?PartnerCompany $record): bool => ! self::isGovernedByAgreements($record))
                                    ->native(false)
                                    ->live()
                                    ->prefixIcon(Heroicon::CreditCard),
                                TextInput::make('agreement_reference')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Referencia del convenio')
                                    ->placeholder('Código o número del acuerdo')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::DocumentDuplicate),
                                TextInput::make('credit_limit_usd')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Límite de crédito aprobado (USD)')
                                    ->helperText('Tope de la línea de crédito pactada en el convenio.')
                                    ->numeric()
                                    ->prefix('$')
                                    ->suffix('USD')
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->visible(fn (Get $get): bool => self::usesCreditLimit($get('payment_modality')))
                                    ->required(fn (Get $get): bool => self::usesCreditLimit($get('payment_modality')))
                                    ->live(onBlur: true)
                                    ->prefixIcon(Heroicon::Banknotes),
                                TextInput::make('assigned_credit_limit')
                                    ->label('Saldo de crédito disponible (USD)')
                                    ->helperText('Se descuenta con cada pedido a crédito finalizado. Al crear, si lo deja vacío inicia igual al límite. Auméntelo cuando el aliado abone.')
                                    ->numeric()
                                    ->prefix('$')
                                    ->suffix('USD')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->nullable()
                                    ->lte('credit_limit_usd')
                                    ->validationMessages([
                                        'lte' => 'El saldo disponible no puede superar el límite de crédito aprobado.',
                                    ])
                                    ->visible(fn (Get $get): bool => self::usesCreditLimit($get('payment_modality')))
                                    ->prefixIcon(Heroicon::Wallet),
                                TextInput::make('profit_percentage_a')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Ganancia % A')
                                    ->helperText('Campo libre para porcentaje de ganancia del aliado.')
                                    ->numeric()
                                    ->suffix('%')
                                    ->step('any')
                                    ->nullable()
                                    ->dehydrateStateUsing(fn (mixed $state): ?float => $state === '' || $state === null
                                        ? null
                                        : (float) $state)
                                    ->prefixIcon(Heroicon::Calculator),
                                TextInput::make('discount_percentage')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Descuento %')
                                    ->helperText('Campo libre para porcentaje de descuento del aliado.')
                                    ->numeric()
                                    ->suffix('%')
                                    ->step('any')
                                    ->nullable()
                                    ->dehydrateStateUsing(fn (mixed $state): ?float => $state === '' || $state === null
                                        ? null
                                        : (float) $state)
                                    ->prefixIcon(Heroicon::Calculator),
                                DatePicker::make('date_created')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Fecha de creación del convenio')
                                    ->helperText('Fecha en que se formaliza o registra el convenio con el aliado.')
                                    ->required(fn (?PartnerCompany $record): bool => ! self::isGovernedByAgreements($record))
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->prefixIcon(Heroicon::CalendarDays)
                                    ->live(),
                                DatePicker::make('agreement_expires_on')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Fecha de vencimiento del convenio')
                                    ->helperText(fn (?PartnerCompany $record): string => self::isGovernedByAgreements($record)
                                        ? 'Tomada del convenio vigente. Para extenderla, renueve el convenio desde la ficha del aliado.'
                                        : 'Al guardar se registra el convenio inicial en borrador para adjuntar el contrato y aprobarlo.')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->prefixIcon(Heroicon::CalendarDateRange)
                                    ->afterOrEqual('date_created')
                                    ->validationMessages(['after_or_equal' => 'El vencimiento no puede ser anterior a la fecha de creación del convenio.'])
                                    ->live()
                                    ->hintActions(collect(['6 meses' => 6, '1 año' => 12, '2 años' => 24])
                                        ->map(fn (int $months, string $label): Action => Action::make('expiresIn'.$months)
                                            ->label('+'.$label)
                                            ->link()
                                            ->size('xs')
                                            ->hidden(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                            ->action(fn (Get $get, Set $set) => $set('agreement_expires_on', self::expiryAfterMonths($get('date_created'), $months))))
                                        ->values()
                                        ->all())
                                    ->nullable(),
                                TextEntry::make('agreement_term_status')
                                    ->hiddenLabel()
                                    ->state(fn (Get $get): string => AgreementTermStatus::fromDates($get('date_created'), $get('agreement_expires_on'))->summary())
                                    ->icon(fn (Get $get): Heroicon => match (AgreementTermStatus::fromDates($get('date_created'), $get('agreement_expires_on'))->tone) {
                                        'ok' => Heroicon::CheckCircle,
                                        'warning' => Heroicon::ExclamationTriangle,
                                        'danger' => Heroicon::XCircle,
                                        default => Heroicon::InformationCircle,
                                    })
                                    ->color(fn (Get $get): string => AgreementTermStatus::fromDates($get('date_created'), $get('agreement_expires_on'))->color())
                                    ->weight('medium')
                                    ->columnSpanFull(),
                                Textarea::make('agreement_terms')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Términos del convenio')
                                    ->placeholder('Condiciones comerciales, plazos y alcance del convenio')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->label('Notas internas')
                                    ->placeholder('Observaciones relevantes para el equipo')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contactos y responsables')
                    ->description('Personas de contacto del aliado. Marque como responsable a quien firma o administra el convenio.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Repeater::make('contacts')
                            ->label('')
                            ->relationship()
                            ->orderColumn('sort')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                    'lg' => 4,
                                ])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nombre completo')
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::UserCircle),
                                        TextInput::make('position')
                                            ->label('Cargo')
                                            ->placeholder('Ej. Gerente de compras')
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::Briefcase),
                                        TextInput::make('email')
                                            ->label('Correo')
                                            ->email()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::Envelope),
                                        TextInput::make('phone')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->placeholder('Solo números')
                                            ->rule('regex:/^[0-9]+$/')
                                            ->validationMessages([
                                                'regex' => 'El teléfono solo puede contener números, sin espacios ni caracteres especiales.',
                                            ])
                                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                                ? preg_replace('/[^0-9]/', '', $state)
                                                : null)
                                            ->maxLength(40)
                                            ->prefixIcon(Heroicon::DevicePhoneMobile),
                                        Toggle::make('is_responsible')
                                            ->label('Responsable del convenio')
                                            ->default(false)
                                            ->inline(false),
                                        Textarea::make('notes')
                                            ->label('Notas')
                                            ->rows(1)
                                            ->columnSpan(['default' => 1, 'sm' => 1, 'lg' => 3]),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Añadir contacto')
                            ->collapsible()
                            ->itemLabel(fn (?array $state): ?string => filled($state['name'] ?? null)
                                ? (string) $state['name'].(! empty($state['is_responsible']) ? ' · Responsable' : '')
                                : 'Nuevo contacto')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Documentos del aliado')
                    ->description('Carta de convenio, RIF, cédula del representante legal, Registro Mercantil y otros soportes. PDF o imagen, máximo 10 MB.')
                    ->icon(Heroicon::PaperClip)
                    ->schema([
                        Repeater::make('documents')
                            ->label('')
                            ->relationship()
                            ->orderColumn('sort')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'sm' => 2,
                                ])
                                    ->schema([
                                        Select::make('type')
                                            ->label('Tipo de documento')
                                            ->options(PartnerCompanyDocumentType::options())
                                            ->required()
                                            ->native(false)
                                            ->live(),
                                        DatePicker::make('expires_at')
                                            ->label('Vence el')
                                            ->helperText('Opcional.')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),
                                        FileUpload::make('file_path')
                                            ->label('Archivo')
                                            ->disk(PartnerCompanyDocument::DISK)
                                            ->directory(PartnerCompanyDocument::DIRECTORY)
                                            ->visibility('private')
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(10240)
                                            ->storeFileNamesIn('original_name')
                                            ->openable()
                                            ->downloadable()
                                            ->required()
                                            ->columnSpanFull(),
                                        Textarea::make('notes')
                                            ->label('Notas')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Añadir documento')
                            ->collapsible()
                            ->itemLabel(fn (?array $state): ?string => PartnerCompanyDocumentType::tryFrom((string) ($state['type'] ?? ''))?->label() ?? 'Nuevo documento')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Usuarios del panel (aliado)')
                    ->description(fn (): string => 'Acceso a dale · Aliados con su correo. Indique al menos un administrador del panel: él creará al resto de su equipo y asignará permisos. Cada usuario nuevo entra con la clave estándar «'.PartnerPanelUserService::defaultPassword().'» y decide si la cambia. En rol Gerente: desde el 3er usuario quedan pendientes de activación por Administrador.')
                    ->icon(Heroicon::UserGroup)
                    ->visible(fn (): bool => PartnerCompanyAlliedUsersFormSync::actorManagesPartnerUsers())
                    ->schema([
                        Repeater::make('partner_users')
                            ->label('')
                            ->schema([
                                Hidden::make('user_id')
                                    ->dehydrated(),
                                Grid::make(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nombre completo')
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::UserCircle),
                                        TextInput::make('email')
                                            ->label('Correo electrónico (usuario)')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::Envelope)
                                            ->autocomplete(false),
                                    ]),
                                TextEntry::make('access_status')
                                    ->hiddenLabel()
                                    ->state(fn (Get $get): ?string => self::partnerUserStatusLine($get('user_id')))
                                    ->visible(fn (Get $get): bool => filled($get('user_id')))
                                    ->color('gray')
                                    ->icon(Heroicon::InformationCircle),
                                Toggle::make('is_admin')
                                    ->label('Administrador del panel dale')
                                    ->helperText('Tiene todos los permisos y es quien crea usuarios de su empresa y les asigna permisos. Solo Farmadoc designa administradores.')
                                    ->onIcon(Heroicon::Star)
                                    ->live()
                                    ->default(false)
                                    ->inline(false),
                                CheckboxList::make('permissions')
                                    ->label('Permisos')
                                    ->hidden(fn (Get $get): bool => (bool) $get('is_admin'))
                                    ->options(fn (Get $get, Component $livewire): array => PartnerPanelPermission::assignableOptions(self::permissionCompany($get, $livewire)))
                                    ->descriptions(fn (Get $get, Component $livewire): array => PartnerPanelPermission::assignableDescriptions(self::permissionCompany($get, $livewire)))
                                    ->default(array_map(fn (PartnerPanelPermission $permission): string => $permission->value, PartnerPanelPermission::defaults()))
                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                    ->bulkToggleable()
                                    ->required()
                                    ->validationMessages(['required' => 'Marque al menos un permiso.']),
                                Toggle::make('is_active')
                                    ->label('Acceso activo')
                                    ->helperText('Desactívelo para suspender el acceso sin borrar el usuario.')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->extraItemActions([
                                Action::make('sendPartnerAccess')
                                    ->label('Enviar acceso por correo')
                                    ->tooltip('Enviar acceso por correo')
                                    ->icon(Heroicon::PaperAirplane)
                                    ->color('primary')
                                    ->visible(fn (array $arguments, Repeater $component): bool => filled($component->getRawItemState($arguments['item'])['user_id'] ?? null))
                                    ->requiresConfirmation()
                                    ->modalHeading('Enviar acceso por correo')
                                    ->modalDescription('Se enviará un enlace para crear (o cambiar) la clave. Los enlaces anteriores dejan de funcionar.')
                                    ->modalSubmitActionLabel('Enviar')
                                    ->action(function (array $arguments, Repeater $component): void {
                                        $userId = (int) ($component->getRawItemState($arguments['item'])['user_id'] ?? 0);
                                        $record = $component->getRecord();
                                        $user = User::query()->find($userId);

                                        if (! $user instanceof User || ! $record instanceof PartnerCompany || (int) $user->partner_company_id !== (int) $record->getKey()) {
                                            Notification::make()->title('Usuario no válido para esta compañía aliada.')->danger()->send();

                                            return;
                                        }

                                        try {
                                            app(PartnerPanelUserService::class)->sendAccess($user, request()->user());
                                        } catch (ValidationException $exception) {
                                            Notification::make()->title(collect($exception->errors())->flatten()->first())->warning()->send();

                                            return;
                                        }

                                        Notification::make()
                                            ->title('Acceso enviado')
                                            ->body("Se envió el enlace a {$user->email}.")
                                            ->success()
                                            ->send();
                                    }),
                                Action::make('copyPartnerAccessLink')
                                    ->label('Copiar enlace de acceso')
                                    ->tooltip('Copiar enlace de acceso')
                                    ->icon(Heroicon::Link)
                                    ->color('gray')
                                    ->visible(fn (array $arguments, Repeater $component): bool => request()->user()?->isAdministrator() === true
                                        && filled($component->getRawItemState($arguments['item'])['user_id'] ?? null))
                                    ->requiresConfirmation()
                                    ->modalIcon(Heroicon::Link)
                                    ->modalHeading('Copiar enlace de acceso')
                                    ->modalDescription('Genera el enlace para que el usuario cree su clave, sin enviar correo. Es personal, de un solo uso y vence en '.PartnerPanel::accessLinkHours().' horas. Los enlaces anteriores dejan de funcionar. Queda registrado en la auditoría.')
                                    ->modalSubmitActionLabel('Generar y copiar')
                                    ->action(function (array $arguments, Repeater $component, Component $livewire): void {
                                        $userId = (int) ($component->getRawItemState($arguments['item'])['user_id'] ?? 0);
                                        $record = $component->getRecord();
                                        $user = User::query()->with('partnerCompany')->find($userId);
                                        $actor = request()->user();

                                        if (! $user instanceof User || ! $actor instanceof User || ! $record instanceof PartnerCompany || (int) $user->partner_company_id !== (int) $record->getKey()) {
                                            Notification::make()->title('Usuario no válido para esta compañía aliada.')->danger()->send();

                                            return;
                                        }

                                        try {
                                            $url = app(PartnerPanelUserService::class)->accessLink($user, $actor);
                                        } catch (ValidationException $exception) {
                                            Notification::make()->title(collect($exception->errors())->flatten()->first())->warning()->send();

                                            return;
                                        }

                                        $livewire->js('navigator.clipboard?.writeText('.Js::from($url).')');

                                        Notification::make()
                                            ->title('Enlace copiado')
                                            ->body(new HtmlString('Enlace de <strong>'.e($user->email).'</strong> copiado al portapapeles. Si no se copió, selecciónelo aquí:<br><code style="word-break: break-all; user-select: all;">'.e($url).'</code>'))
                                            ->success()
                                            ->persistent()
                                            ->send();
                                    }),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Añadir usuario')
                            ->collapsible()
                            ->itemLabel(fn (?array $state): ?string => (filled($state['name'] ?? null) ? (string) $state['name'] : 'Nuevo usuario')
                                .(($state['is_admin'] ?? false) ? ' ★ Administrador' : ''))
                            ->columnSpanFull()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Usuarios responsables de la creación y última actualización del registro.')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('created_by')
                                    ->label('Creado por')
                                    ->maxLength(255)
                                    ->placeholder('Usuario creador')
                                    ->prefixIcon(Heroicon::User),
                                TextInput::make('updated_by')
                                    ->label('Actualizado por')
                                    ->maxLength(255)
                                    ->placeholder('Usuario editor')
                                    ->prefixIcon(Heroicon::PencilSquare),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function usesCreditLimit(mixed $modality): bool
    {
        $modality = $modality instanceof PartnerCompanyPaymentModality
            ? $modality
            : PartnerCompanyPaymentModality::tryFrom((string) $modality);

        return $modality?->usesCreditLimit() ?? false;
    }

    /**
     * Vencimiento sugerido: el día anterior a cumplir los meses desde la fecha de creación (o desde hoy).
     */
    private static function expiryAfterMonths(mixed $startsOn, int $months): string
    {
        $start = filled($startsOn) ? CarbonImmutable::parse((string) $startsOn) : CarbonImmutable::today();

        return $start->addMonthsNoOverflow($months)->subDay()->toDateString();
    }

    private static function isGovernedByAgreements(?PartnerCompany $record): bool
    {
        return $record instanceof PartnerCompany && $record->isGovernedByAgreements();
    }

    /**
     * Estado del acceso de un usuario ya creado (invitación, activación, último ingreso).
     */
    private static function partnerUserStatusLine(mixed $userId): ?string
    {
        if (! is_numeric($userId)) {
            return null;
        }

        $user = User::query()->find((int) $userId);

        if (! $user instanceof User) {
            return null;
        }

        $status = $user->partnerAccessStatus();
        $parts = [$status['label']];

        if ($status['key'] === 'pending') {
            $parts[] = $user->partner_access_sent_at === null
                ? 'aún no se le ha enviado el acceso'
                : 'acceso enviado el '.$user->partner_access_sent_at->format('d/m/Y h:i a');
        }

        if ($status['key'] !== 'suspended' && $user->usesDefaultPartnerPassword()) {
            $parts[] = 'usa la clave estándar';
        }

        if ($user->partner_last_login_at !== null) {
            $parts[] = 'último ingreso '.$user->partner_last_login_at->format('d/m/Y h:i a');
        }

        return implode(' · ', $parts);
    }

    /**
     * La casilla de la compañía manda cuando la edita un administrador.
     * El resto ve el valor ya guardado, sin poder habilitar el permiso de empresa.
     */
    private static function permissionCompany(Get $get, Component $livewire): PartnerCompany
    {
        $saved = null;

        if ($livewire instanceof EditRecord) {
            $candidate = $livewire->getRecord();
            $saved = $candidate instanceof PartnerCompany ? $candidate : null;
        }
        $actor = request()->user();
        $company = new PartnerCompany;

        $company->can_place_orders = $actor instanceof User && $actor->isAdministrator()
            ? self::formAllowsPrincipalInventoryOrders($get)
            : (bool) $saved?->can_place_orders;

        return $company;
    }

    private static function formAllowsPrincipalInventoryOrders(Get $get): bool
    {
        foreach ([
            'can_place_orders',
            '../can_place_orders',
            '../../can_place_orders',
            '../../../can_place_orders',
            '../../../../can_place_orders',
        ] as $path) {
            $value = $get($path);

            if (is_bool($value)) {
                return $value;
            }
        }

        $absolute = $get('/data.can_place_orders', true);

        return is_bool($absolute) ? $absolute : false;
    }
}
