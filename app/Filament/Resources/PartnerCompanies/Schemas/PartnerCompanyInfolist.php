<?php

namespace App\Filament\Resources\PartnerCompanies\Schemas;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Enums\UncoveredMedicineSyncStatus;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Filament\Resources\PartnerAgreements\Schemas\PartnerCompanyAgreementInfolist;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\PartnerCompanyDocument;
use App\Models\User;
use App\Support\Partners\AgreementTermStatus;
use App\Support\Partners\PartnerPlanCards;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PartnerCompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación de la compañía')
                    ->description('Datos legales y de convenio para identificar al aliado comercial.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Código')
                                    ->placeholder('—')
                                    ->badge()
                                    ->color('primary')
                                    ->copyable(),
                                TextEntry::make('legal_name')
                                    ->label('Razón social')
                                    ->placeholder('—')
                                    ->weight('medium')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('trade_name')
                                    ->label('Nombre comercial')
                                    ->placeholder('—'),
                                TextEntry::make('tax_id')
                                    ->label('RIF')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('client_type')
                                    ->label('Tipo de cliente')
                                    ->formatStateUsing(fn (mixed $state): string => PartnerCompanyClientType::tryLabel($state))
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                                TextEntry::make('branches.name')
                                    ->label('Sucursales')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('Sin sucursal asignada')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                IconEntry::make('is_active')
                                    ->label('Estado')
                                    ->boolean()
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                IconEntry::make('can_place_orders')
                                    ->label('Pedidos del inventario')
                                    ->boolean()
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('gray'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Medicamentos no cubiertos')
                    ->description('Lo que Tu Dr. Group está gestionando para este aliado. La consulta a la API solo ocurre al pulsar «Actualizar información».')
                    ->icon(Heroicon::Beaker)
                    ->visible(fn (PartnerCompany $record): bool => $record->hasUncoveredMedicinesAccess())
                    ->headerActions([
                        Action::make('manageUncoveredMedicines')
                            ->label('Gestión de medicamentos')
                            ->icon(Heroicon::ArrowTopRightOnSquare)
                            ->url(fn (PartnerCompany $record): string => PartnerCompanyResource::getUrl('uncovered-medicines', ['record' => $record])),
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->schema([
                                TextEntry::make('uncoveredMedicineConnection.last_synced_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y h:i a')
                                    ->placeholder('Sin consultar'),
                                TextEntry::make('uncoveredMedicineConnection.sync_status')
                                    ->label('Sincronización')
                                    ->badge()
                                    ->placeholder('Sin consultar')
                                    ->formatStateUsing(fn (mixed $state): string => UncoveredMedicineSyncStatus::tryLabel($state))
                                    ->color(function (mixed $state): string {
                                        if ($state instanceof UncoveredMedicineSyncStatus) {
                                            return $state->color();
                                        }

                                        return UncoveredMedicineSyncStatus::tryFrom((string) $state)?->color() ?? 'gray';
                                    }),
                                TextEntry::make('uncoveredMedicineConnection.rate_limit_remaining')
                                    ->label('Cupo restante por minuto')
                                    ->placeholder('—')
                                    ->formatStateUsing(function (mixed $state, mixed $record): string {
                                        $connection = $record instanceof PartnerCompany
                                            ? $record->uncoveredMedicineConnection
                                            : null;
                                        if ($state === null) {
                                            return '—';
                                        }

                                        $limit = $connection?->rate_limit_limit;

                                        return $limit !== null ? $state.' de '.$limit : (string) $state;
                                    }),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Planes corporativos')
                    ->description(fn (PartnerCompany $record): string => self::plansDescription($record))
                    ->icon(Heroicon::ShieldCheck)
                    ->extraAttributes(['class' => 'fi-client-marketing-behavior'])
                    ->headerActions([
                        Action::make('viewPartnerPlans')
                            ->label('Ver en Planes')
                            ->icon(Heroicon::ArrowTopRightOnSquare)
                            ->visible(fn (): bool => PlanResource::canViewAny())
                            ->url(fn (PartnerCompany $record): string => PlanResource::getUrl('index', [
                                'filters' => ['partner_company' => ['value' => $record->getKey()]],
                            ])),
                    ])
                    ->schema([
                        TextEntry::make('plan_cards')
                            ->hiddenLabel()
                            ->state(fn (PartnerCompany $record): string => view('filament.partner-companies.plan-cards', [
                                'cards' => PartnerPlanCards::for($record, linkToPlans: PlanResource::canViewAny()),
                            ])->render())
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Contactos y responsables')
                    ->description('Personas de contacto del aliado. Se gestionan desde Editar compañía aliada.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        RepeatableEntry::make('contacts')
                            ->hiddenLabel()
                            ->placeholder('Sin contactos registrados.')
                            ->table([
                                TableColumn::make('Nombre'),
                                TableColumn::make('Cargo'),
                                TableColumn::make('Correo'),
                                TableColumn::make('Teléfono'),
                                TableColumn::make('Rol')
                                    ->alignment(Alignment::Center),
                            ])
                            ->schema([
                                TextEntry::make('name')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('position')
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('phone')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('is_responsible')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Responsable' : 'Contacto')
                                    ->badge()
                                    ->color(fn (?bool $state): string => $state ? 'success' : 'gray'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Documentos del aliado')
                    ->description('Carta de convenio, RIF, cédula del representante legal, Registro Mercantil y otros soportes.')
                    ->icon(Heroicon::PaperClip)
                    ->schema([
                        RepeatableEntry::make('documents')
                            ->hiddenLabel()
                            ->placeholder('Sin documentos cargados.')
                            ->table([
                                TableColumn::make('Documento'),
                                TableColumn::make('Archivo'),
                                TableColumn::make('Vence'),
                            ])
                            ->schema([
                                TextEntry::make('type')
                                    ->formatStateUsing(fn (mixed $state): string => $state?->label() ?? '—')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('original_name')
                                    ->state(fn (PartnerCompanyDocument $record): string => $record->displayName())
                                    ->url(fn (PartnerCompanyDocument $record): string => $record->downloadUrl())
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::ArrowTopRightOnSquare)
                                    ->color('primary'),
                                TextEntry::make('expires_at')
                                    ->date('d/m/Y')
                                    ->placeholder('—')
                                    ->color(fn (PartnerCompanyDocument $record): ?string => $record->isExpired() ? 'danger' : null),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Usuarios del panel')
                    ->description('Cuentas que inician sesión en Farmadoc y operan en nombre de esta compañía aliada. Altas, contraseñas y estado activo se gestionan desde Editar compañía aliada.')
                    ->icon(Heroicon::Users)
                    ->iconColor('primary')
                    ->extraAttributes([
                        'class' => 'fi-partner-company-panel-users-section',
                    ])
                    ->schema([
                        TextEntry::make('id')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->formatStateUsing(function (mixed $state, ?Model $record): HtmlString {
                                if (! $record instanceof PartnerCompany) {
                                    return new HtmlString('');
                                }

                                $users = $record->relationLoaded('alliedPanelUsers')
                                    ? $record->alliedPanelUsers
                                    : $record->alliedPanelUsers()->get();

                                $total = $users->count();
                                $active = $users->filter(fn (User $u): bool => (bool) $u->partner_user_is_active)->count();
                                $inactive = max(0, $total - $active);

                                return new HtmlString(
                                    '<div class="fi-partner-company-panel-users-hero" role="region" aria-label="Resumen de usuarios del panel">'
                                    .'<div class="fi-partner-company-panel-users-hero__inner">'
                                    .'<div class="fi-partner-company-panel-users-hero__intro">'
                                    .'<span class="fi-partner-company-panel-users-hero__kicker">'.e('Acceso operativo').'</span>'
                                    .'<p class="fi-partner-company-panel-users-hero__lead">'.e('Resumen de cuentas vinculadas a este aliado. Solo los usuarios activos pueden entrar al panel.').'</p>'
                                    .'</div>'
                                    .'<div class="fi-partner-company-panel-users-hero__stats">'
                                    .'<div class="fi-partner-company-panel-users-hero__stat" data-tone="total">'
                                    .'<span class="fi-partner-company-panel-users-hero__stat-value" aria-hidden="true">'.e((string) $total).'</span>'
                                    .'<span class="fi-partner-company-panel-users-hero__stat-label">'.e('Total').'</span>'
                                    .'</div>'
                                    .'<div class="fi-partner-company-panel-users-hero__stat" data-tone="active">'
                                    .'<span class="fi-partner-company-panel-users-hero__stat-value" aria-hidden="true">'.e((string) $active).'</span>'
                                    .'<span class="fi-partner-company-panel-users-hero__stat-label">'.e('Activos').'</span>'
                                    .'</div>'
                                    .'<div class="fi-partner-company-panel-users-hero__stat" data-tone="inactive">'
                                    .'<span class="fi-partner-company-panel-users-hero__stat-value" aria-hidden="true">'.e((string) $inactive).'</span>'
                                    .'<span class="fi-partner-company-panel-users-hero__stat-label">'.e('Inactivos').'</span>'
                                    .'</div>'
                                    .'</div></div></div>'
                                );
                            })
                            ->extraEntryWrapperAttributes([
                                'class' => 'fi-partner-company-panel-users-hero-entry',
                            ]),
                        RepeatableEntry::make('alliedPanelUsers')
                            ->label('Cuentas vinculadas')
                            ->placeholder('Sin usuarios de panel todavía. Abra «Editar compañía aliada» para crear cuentas con correo y contraseña.')
                            ->table([
                                TableColumn::make('Usuario')
                                    ->width('26%'),
                                TableColumn::make('Correo')
                                    ->width('36%'),
                                TableColumn::make('Código')
                                    ->width('14%')
                                    ->alignment(Alignment::Center),
                                TableColumn::make('Estado')
                                    ->width('12%')
                                    ->alignment(Alignment::Center),
                            ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('')
                                    ->icon(Heroicon::UserCircle)
                                    ->iconColor('gray')
                                    ->weight(FontWeight::SemiBold)
                                    ->size(TextSize::Medium),
                                TextEntry::make('email')
                                    ->label('')
                                    ->icon(Heroicon::Envelope)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('partner_company_code')
                                    ->label('')
                                    ->placeholder('—')
                                    ->alignment(Alignment::Center)
                                    ->badge()
                                    ->color('primary')
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('partner_user_is_active')
                                    ->label('')
                                    ->alignment(Alignment::Center)
                                    ->formatStateUsing(fn (?bool $state): string => $state ? 'Activo' : 'Inactivo')
                                    ->badge()
                                    ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                                    ->icon(fn (?bool $state): Heroicon => $state ? Heroicon::CheckCircle : Heroicon::XCircle),
                            ])
                            ->columnSpanFull()
                            ->extraEntryWrapperAttributes([
                                'class' => 'fi-partner-company-panel-users-repeatable',
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Acuerdo de negociación')
                    ->description('Modalidad de pago, límite aprobado y saldo disponible en USD. Los pedidos a crédito finalizados descuentan el saldo y dejan constancia en el histórico de movimientos del panel aliado.')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('success')
                    ->extraAttributes([
                        'class' => 'fi-partner-company-credit-section',
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('payment_modality')
                                    ->label('Modalidad de pago')
                                    ->formatStateUsing(fn (mixed $state): string => PartnerCompanyPaymentModality::tryLabel($state))
                                    ->badge()
                                    ->color(fn (mixed $state): string => $state instanceof PartnerCompanyPaymentModality ? $state->color() : 'gray')
                                    ->placeholder('—'),
                                TextEntry::make('credit_limit_usd')
                                    ->label('Límite de crédito aprobado')
                                    ->money('USD')
                                    ->placeholder('No aplica')
                                    ->weight(FontWeight::SemiBold),
                            ]),
                        TextEntry::make('assigned_credit_limit')
                            ->label('Saldo disponible')
                            ->money('USD')
                            ->placeholder('Sin línea de crédito asignada')
                            ->icon(Heroicon::CurrencyDollar)
                            ->iconColor('success')
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->color(fn ($state): ?string => filled($state) ? 'success' : null)
                            ->badge(fn ($state): bool => filled($state))
                            ->hint('Si está vacío, no hay cupo formal; defínalo desde Editar compañía aliada.')
                            ->hintIcon(Heroicon::InformationCircle)
                            ->hintColor('gray')
                            ->columnSpanFull()
                            ->extraEntryWrapperAttributes([
                                'class' => 'fi-partner-company-credit-entry',
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('profit_percentage_a')
                                    ->label('Ganancia % A')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state): string => filled($state) ? ((string) $state).'%' : '—'),
                                TextEntry::make('discount_percentage')
                                    ->label('Descuento %')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn ($state): string => filled($state) ? ((string) $state).'%' : '—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Convenio vigente')
                    ->description('Renovaciones, modificaciones y vencimientos se gestionan en «Convenios y renovaciones».')
                    ->icon(Heroicon::DocumentCheck)
                    ->headerActions([
                        Action::make('manageAgreements')
                            ->label('Ver convenios')
                            ->icon(Heroicon::ArrowTopRightOnSquare)
                            ->url(fn (PartnerCompany $record): string => PartnerCompanyAgreementResource::getUrl('index', [
                                'filters' => ['partner_company_id' => ['value' => $record->getKey()]],
                            ])),
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('currentAgreement.number')
                                    ->label('Número')
                                    ->placeholder('Sin convenio vigente')
                                    ->badge()
                                    ->color('success')
                                    ->url(fn (PartnerCompany $record): ?string => $record->currentAgreement
                                        ? PartnerCompanyAgreementResource::getUrl('view', ['record' => $record->currentAgreement])
                                        : null),
                                TextEntry::make('currentAgreement.type')
                                    ->label('Tipo')
                                    ->formatStateUsing(fn (mixed $state): string => PartnerAgreementType::tryLabel($state))
                                    ->placeholder('—'),
                                TextEntry::make('currentAgreement.ends_on')
                                    ->label('Vence')
                                    ->date('d/m/Y')
                                    ->placeholder('—')
                                    ->hint(fn (PartnerCompany $record): ?string => $record->currentAgreement
                                        ? PartnerCompanyAgreementInfolist::daysLeftLabel($record->currentAgreement)
                                        : null),
                                IconEntry::make('currentAgreement.auto_renews')
                                    ->label('Renovación automática')
                                    ->boolean()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Vigencia del convenio')
                    ->description(fn (PartnerCompany $record): string => $record->isGovernedByAgreements()
                        ? 'Fechas del convenio vigente. Se actualizan al aprobar una renovación.'
                        : 'Fechas registradas en la ficha del aliado.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        TextEntry::make('agreement_term')
                            ->hiddenLabel()
                            ->state(fn (PartnerCompany $record): string => view('filament.partner-companies.agreement-term', [
                                'term' => $record->agreementTermStatus(),
                            ])->render())
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Historial de convenios')
                    ->description(fn (PartnerCompany $record): string => self::agreementHistoryDescription($record))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsible()
                    ->headerActions([
                        Action::make('renewAgreement')
                            ->label('Renovar convenio')
                            ->icon(Heroicon::ArrowPath)
                            ->color('primary')
                            ->visible(fn (PartnerCompany $record): bool => PartnerCompanyAgreementResource::canCreate() && self::canRenew($record))
                            ->url(fn (PartnerCompany $record): string => PartnerCompanyAgreementResource::getUrl('create', [
                                'partner' => $record->getKey(),
                                'type' => PartnerAgreementType::Renewal->value,
                            ])),
                        Action::make('openInitialDraft')
                            ->label('Completar convenio inicial')
                            ->icon(Heroicon::PaperClip)
                            ->color('warning')
                            ->visible(fn (PartnerCompany $record): bool => PartnerCompanyAgreementResource::canViewAny() && self::initialDraft($record) !== null)
                            ->url(fn (PartnerCompany $record): ?string => ($draft = self::initialDraft($record)) === null
                                ? null
                                : PartnerCompanyAgreementResource::getUrl('view', ['record' => $draft])),
                    ])
                    ->schema([
                        TextEntry::make('agreement_history')
                            ->hiddenLabel()
                            ->state(fn (PartnerCompany $record): string => view('filament.partner-companies.agreement-history', [
                                'items' => self::agreementHistory($record),
                            ])->render())
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Términos del convenio')
                    ->description('Condiciones legales y comerciales acordadas. Revise este bloque antes de operar con el aliado.')
                    ->icon(Heroicon::Scale)
                    ->iconColor('warning')
                    ->extraAttributes([
                        'class' => 'fi-partner-company-agreement-section',
                    ])
                    ->schema([
                        Grid::make(['default' => 1])
                            ->schema([
                                TextEntry::make('agreement_reference')
                                    ->label('Referencia del convenio')
                                    ->formatStateUsing(fn (?string $state): string => filled($state)
                                        ? (string) $state
                                        : 'Sin referencia registrada')
                                    ->emptyTooltip('Puede añadirla desde Editar compañía aliada.')
                                    ->badge()
                                    ->color(fn (?string $state): string => filled($state) ? 'warning' : 'gray')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->icon(Heroicon::DocumentDuplicate)
                                    ->iconColor(fn (?string $state): string => filled($state) ? 'warning' : 'gray')
                                    ->copyable(fn (?string $state): bool => filled($state))
                                    ->copyMessage('Referencia copiada')
                                    ->copyableState(fn (?string $state): ?string => filled($state) ? (string) $state : null)
                                    ->copyMessageDuration(2500)
                                    ->hintColor('gray')
                                    ->columnSpanFull(),
                                TextEntry::make('agreement_terms')
                                    ->label('Texto del convenio')
                                    ->placeholder('No hay texto del convenio registrado.')
                                    ->emptyTooltip('Use Editar para pegar o redactar las condiciones acordadas.')
                                    ->formatStateUsing(function (?string $state): ?HtmlString {
                                        if (! filled($state)) {
                                            return null;
                                        }

                                        return new HtmlString(
                                            '<div class="fi-partner-company-agreement-plain">'.e($state).'</div>'
                                        );
                                    })
                                    ->html()
                                    ->columnSpanFull()
                                    ->size(TextSize::Medium)
                                    ->icon(Heroicon::BookOpen)
                                    ->iconColor('gray')
                                    ->hintColor('gray')
                                    ->copyable(fn (?string $state): bool => filled($state))
                                    ->copyMessage('Texto del convenio copiado')
                                    ->copyMessageDuration(2500)
                                    ->extraAttributes([
                                        'class' => 'fi-partner-company-agreement-terms-entry',
                                    ]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Notas internas del convenio')
                    ->description('Uso exclusivo del equipo. No forma parte del convenio visible al aliado; sirve para acuerdos verbales, riesgos o seguimiento operativo.')
                    ->icon(Heroicon::LockClosed)
                    ->iconColor('info')
                    ->extraAttributes([
                        'class' => 'fi-partner-company-internal-notes-section',
                    ])
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observaciones internas')
                            ->placeholder('No hay notas internas registradas.')
                            ->emptyTooltip('Registre acuerdos verbales o alertas desde Editar compañía aliada.')
                            ->formatStateUsing(function (?string $state): ?HtmlString {
                                if (! filled($state)) {
                                    return null;
                                }

                                return new HtmlString(
                                    '<div class="fi-partner-company-notes-plain">'.e($state).'</div>'
                                );
                            })
                            ->html()
                            ->columnSpanFull()
                            ->size(TextSize::Medium)
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->iconColor('gray')
                            ->hintColor('gray')
                            ->copyable(fn (?string $state): bool => filled($state))
                            ->copyMessage('Notas copiadas')
                            ->copyMessageDuration(2500)
                            ->extraAttributes([
                                'class' => 'fi-partner-company-internal-notes-entry',
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Contacto y ubicación')
                    ->description('Canales de comunicación y ubicación operativa.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Correo corporativo')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::Envelope)
                                    ->iconColor('gray'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::Phone)
                                    ->iconColor('gray'),
                                TextEntry::make('mobile_phone')
                                    ->label('Celular')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon(Heroicon::DevicePhoneMobile)
                                    ->iconColor('gray'),
                                TextEntry::make('website')
                                    ->label('Sitio web')
                                    ->placeholder('—')
                                    ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::GlobeAlt)
                                    ->iconColor('gray')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                TextEntry::make('country')
                                    ->label('País')
                                    ->placeholder('—'),
                                TextEntry::make('state')
                                    ->label('Estado')
                                    ->placeholder('—'),
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Trazabilidad')
                    ->description('Auditoría de creación y cambios del registro.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Convenios del aliado del más reciente al más antiguo, formateados para la línea de tiempo.
     *
     * @return list<array<string, mixed>>
     */
    private static function agreementHistory(PartnerCompany $partner): array
    {
        $canView = PartnerCompanyAgreementResource::canViewAny();
        $currentId = $partner->currentAgreement?->getKey();

        return $partner->agreements()
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(function (PartnerCompanyAgreement $agreement) use ($canView, $currentId): array {
                $isCurrent = $agreement->getKey() === $currentId;
                $term = AgreementTermStatus::fromDates($agreement->starts_on, $agreement->ends_on);

                return [
                    'number' => $agreement->number ?? 'Sin número',
                    'url' => $canView ? PartnerCompanyAgreementResource::getUrl('view', ['record' => $agreement]) : null,
                    'type' => $agreement->type->label(),
                    'type_color' => $agreement->type->color(),
                    'status' => $agreement->status->label(),
                    'status_color' => $agreement->status->color(),
                    'is_current' => $isCurrent,
                    'is_automatic' => (bool) $agreement->is_automatic,
                    'period' => $agreement->starts_on?->format('d/m/Y').' al '.$agreement->ends_on?->format('d/m/Y'),
                    'days' => $term->totalDays === null ? '' : number_format($term->totalDays, 0, ',', '.').' días de vigencia',
                    'term_label' => $isCurrent ? $term->label : null,
                    'reason' => $agreement->change_reason,
                ];
            })
            ->values()
            ->all();
    }

    private static function agreementHistoryDescription(PartnerCompany $partner): string
    {
        $count = $partner->agreements()->count();
        $renewals = $partner->agreements()->where('type', PartnerAgreementType::Renewal->value)->count();

        if ($count === 0) {
            return 'Sin convenios registrados.';
        }

        return ($count === 1 ? '1 convenio' : "{$count} convenios")
            .' · '.($renewals === 1 ? '1 renovación' : "{$renewals} renovaciones").'.';
    }

    /**
     * Se renueva a partir de un convenio aprobado, vigente o vencido, sin otro trámite en curso.
     */
    private static function canRenew(PartnerCompany $partner): bool
    {
        $agreements = $partner->agreements()->get(['id', 'status']);

        $hasBase = $agreements->contains(fn (PartnerCompanyAgreement $agreement): bool => in_array($agreement->status, [
            PartnerAgreementStatus::Approved,
            PartnerAgreementStatus::Active,
            PartnerAgreementStatus::Expired,
        ], true));

        $inFlight = $agreements->contains(fn (PartnerCompanyAgreement $agreement): bool => in_array($agreement->status, [
            PartnerAgreementStatus::Draft,
            PartnerAgreementStatus::PendingApproval,
        ], true));

        return $hasBase && ! $inFlight;
    }

    private static function initialDraft(PartnerCompany $partner): ?PartnerCompanyAgreement
    {
        return $partner->agreements()
            ->where('type', PartnerAgreementType::Initial->value)
            ->whereIn('status', [PartnerAgreementStatus::Draft->value, PartnerAgreementStatus::Rejected->value, PartnerAgreementStatus::PendingApproval->value])
            ->latest('id')
            ->first();
    }

    private static function plansDescription(PartnerCompany $record): string
    {
        $count = $record->plans()->count();

        return match ($count) {
            0 => 'Sin planes asignados.',
            1 => '1 plan asignado a este aliado.',
            default => "{$count} planes asignados a este aliado.",
        };
    }
}
