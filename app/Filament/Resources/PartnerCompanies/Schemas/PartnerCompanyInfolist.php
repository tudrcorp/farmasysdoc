<?php

namespace App\Filament\Resources\PartnerCompanies\Schemas;

use App\Models\PartnerCompany;
use App\Models\User;
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
                                    ->label('NIT / ID fiscal')
                                    ->placeholder('—')
                                    ->copyable(),
                                IconEntry::make('is_active')
                                    ->label('Estado')
                                    ->boolean()
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),
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
                Section::make('Crédito del aliado')
                    ->description('Saldo disponible en USD. Los pedidos a crédito en «En proceso» descuentan este valor y dejan constancia en el histórico de movimientos del panel aliado.')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('success')
                    ->extraAttributes([
                        'class' => 'fi-partner-company-credit-section',
                    ])
                    ->schema([
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
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Vigencia del convenio')
                    ->description('Fechas asociadas al acuerdo con el aliado.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('date_created')
                                    ->label('Fecha de creación del convenio')
                                    ->date('d/m/Y')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CalendarDays)
                                    ->iconColor('gray'),
                                TextEntry::make('date_updated')
                                    ->label('Fecha de actualización del convenio')
                                    ->date('d/m/Y')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ArrowPath)
                                    ->iconColor('gray'),
                            ]),
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
                                    ->label('Departamento / estado')
                                    ->placeholder('—'),
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
                Section::make('Contacto comercial y trazabilidad')
                    ->description('Responsables de gestión y auditoría de cambios.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('contact_name')
                                    ->label('Nombre contacto')
                                    ->placeholder('—'),
                                TextEntry::make('contact_email')
                                    ->label('Correo contacto')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('contact_phone')
                                    ->label('Teléfono contacto')
                                    ->placeholder('—')
                                    ->copyable(),
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
}
