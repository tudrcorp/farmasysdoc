<?php

namespace App\Filament\Resources\PartnerCompanies\Schemas;

use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyDocumentType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyDocument;
use App\Models\State;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                                    ->prefixIcon(Heroicon::CalendarDays),
                                DatePicker::make('date_updated')
                                    ->disabled(fn (?PartnerCompany $record): bool => self::isGovernedByAgreements($record))
                                    ->label('Fecha de actualización del convenio')
                                    ->helperText('Opcional. Use cuando el convenio se modifique o renueve.')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->prefixIcon(Heroicon::ArrowPath)
                                    ->nullable(),
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
                    ->description('Administradores y Gerentes pueden gestionar usuarios del aliado. En rol Gerente: desde el 3er usuario quedan pendientes de activación por Administrador.')
                    ->icon(Heroicon::UserGroup)
                    ->visible(function (): bool {
                        $authUser = request()->user();

                        return $authUser instanceof User
                            && ($authUser->isAdministrator() || $authUser->isManager());
                    })
                    ->schema([
                        Repeater::make('partner_users')
                            ->label('')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'lg' => 2,
                                ])
                                    ->schema([
                                        Hidden::make('user_id')
                                            ->dehydrated(),
                                        TextInput::make('name')
                                            ->label('Nombre completo')
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::UserCircle),
                                        TextInput::make('email')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->prefixIcon(Heroicon::Envelope)
                                            ->autocomplete(false),
                                        TextInput::make('password')
                                            ->label('Contraseña')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255)
                                            ->minLength(8)
                                            ->helperText(fn (Get $get): string => filled($get('user_id'))
                                                ? 'Opcional al editar. Déjela en blanco para no cambiar la contraseña.'
                                                : 'Mínimo 8 caracteres. Se usará para iniciar sesión en el panel.')
                                            ->required(fn (Get $get): bool => blank($get('user_id')))
                                            ->dehydrated(fn (Get $get): bool => filled($get('password')))
                                            ->prefixIcon(Heroicon::Key),
                                        Toggle::make('is_active')
                                            ->label('Usuario activo')
                                            ->helperText('Si está desactivado, no podrá acceder al panel como usuario de esta compañía aliada.')
                                            ->default(true)
                                            ->inline(false)
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Añadir usuario')
                            ->collapsible()
                            ->itemLabel(fn (?array $state): ?string => filled($state['name'] ?? null)
                                ? (string) $state['name']
                                : 'Nuevo usuario')
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

    private static function isGovernedByAgreements(?PartnerCompany $record): bool
    {
        return $record instanceof PartnerCompany && $record->isGovernedByAgreements();
    }
}
