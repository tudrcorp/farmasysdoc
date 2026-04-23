<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\User;
use App\Support\Users\UserRoleLabels;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Perfil')
                    ->description('Identidad y sucursal asignada.')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre completo')
                                    ->icon(Heroicon::User)
                                    ->weight('medium'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->icon(Heroicon::Envelope)
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->placeholder('—'),
                                TextEntry::make('branch_display')
                                    ->label('Sucursal')
                                    ->icon(Heroicon::BuildingOffice2)
                                    ->getStateUsing(function (User $record): ?string {
                                        if ($record->hasGerenciaRole()) {
                                            $record->loadMissing('managedBranches');
                                            $names = $record->managedBranches->pluck('name')->filter()->values()->all();
                                            if ($names !== []) {
                                                return implode(', ', $names);
                                            }
                                        }

                                        $branch = $record->branch;
                                        if (! $branch) {
                                            return null;
                                        }
                                        $name = $branch->name ?? '';
                                        if (filled($branch->code)) {
                                            return $name.' · Código: '.$branch->code;
                                        }

                                        return $name !== '' ? $name : null;
                                    })
                                    ->placeholder('Sin sucursal asignada')
                                    ->url(fn (User $record): ?string => $record->branch_id
                                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                                        : null)
                                    ->openUrlInNewTab(false),
                                TextEntry::make('roles')
                                    ->label('Roles')
                                    ->icon(Heroicon::UserGroup)
                                    ->getStateUsing(function (User $record): ?string {
                                        $roles = $record->roles;
                                        if (! is_array($roles) || $roles === []) {
                                            return null;
                                        }

                                        return collect($roles)
                                            ->filter(fn (mixed $role): bool => filled($role))
                                            ->map(fn (mixed $role): string => UserRoleLabels::label($role))
                                            ->implode(', ');
                                    })
                                    ->placeholder('Sin roles asignados')
                                    ->visible(fn (User $record): bool => is_array($record->roles) && $record->roles !== []),
                                TextEntry::make('delivery_identity_document')
                                    ->label('Cédula de identidad')
                                    ->icon(Heroicon::Identification)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Cédula copiada')
                                    ->visible(fn (User $record): bool => $record->isDeliveryUser()),
                                TextEntry::make('delivery_mobile_phone')
                                    ->label('Teléfono móvil')
                                    ->icon(Heroicon::Phone)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado')
                                    ->visible(fn (User $record): bool => $record->isDeliveryUser()),
                                TextEntry::make('whatsapp_phone')
                                    ->label('WhatsApp')
                                    ->icon(Heroicon::ChatBubbleLeftRight)
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('WhatsApp copiado')
                                    ->visible(fn (User $record): bool => filled($record->whatsapp_phone)),
                                ImageEntry::make('delivery_photo_path')
                                    ->label('Foto para entregas (aliados)')
                                    ->disk('public')
                                    ->height(160)
                                    ->imageHeight(160)
                                    ->circular()
                                    ->visible(fn (User $record): bool => $record->isDeliveryUser() && filled($record->delivery_photo_path)),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Compañía aliada')
                    ->description('Este usuario opera en el panel en nombre de la siguiente compañía aliada.')
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('primary')
                    ->visible(fn (User $record): bool => filled($record->partner_company_id))
                    ->extraAttributes([
                        'class' => 'fi-user-infolist-partner-section',
                    ])
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('partner_company_id')
                                    ->label('ID')
                                    ->numeric()
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::Hashtag)
                                    ->iconColor('primary')
                                    ->copyable()
                                    ->copyMessage('ID copiado'),
                                TextEntry::make('partner_company_code')
                                    ->label('Código del aliado')
                                    ->placeholder('—')
                                    ->badge()
                                    ->color('gray')
                                    ->icon(Heroicon::QrCode)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Código copiado'),
                                TextEntry::make('partner_company_display')
                                    ->label('Empresa')
                                    ->columnSpan(['default' => 1, 'sm' => 2])
                                    ->icon(Heroicon::BuildingLibrary)
                                    ->iconColor('primary')
                                    ->weight(FontWeight::Bold)
                                    ->size(TextSize::Large)
                                    ->placeholder('Compañía no encontrada o eliminada')
                                    ->getStateUsing(function (User $record): ?string {
                                        $company = $record->partnerCompany;
                                        if ($company === null) {
                                            return null;
                                        }
                                        $trade = filled($company->trade_name) ? (string) $company->trade_name : null;
                                        $legal = filled($company->legal_name) ? (string) $company->legal_name : null;
                                        if ($trade !== null && $legal !== null && $trade !== $legal) {
                                            return $trade.' · '.$legal;
                                        }

                                        return $trade ?? $legal;
                                    })
                                    ->url(function (User $record): ?string {
                                        if ($record->partner_company_id === null) {
                                            return null;
                                        }
                                        $company = $record->partnerCompany;
                                        if ($company === null) {
                                            return null;
                                        }
                                        if (! PartnerCompanyResource::canView($company)) {
                                            return null;
                                        }

                                        return PartnerCompanyResource::getUrl('view', ['record' => $record->partner_company_id], isAbsolute: false);
                                    })
                                    ->openUrlInNewTab(false)
                                    ->extraEntryWrapperAttributes([
                                        'class' => 'fi-user-infolist-partner-company-name',
                                    ]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Acceso y seguridad')
                    ->description('Estado del correo y autenticación en dos pasos (2FA).')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                IconEntry::make('email_verified')
                                    ->label('Correo verificado')
                                    ->boolean()
                                    ->getStateUsing(fn (User $record): bool => filled($record->email_verified_at))
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('warning')
                                    ->tooltip(fn (User $record): string => filled($record->email_verified_at)
                                        ? 'El usuario puede usar todas las funciones que exijan correo verificado.'
                                        : 'Pendiente de verificación por enlace de correo.'),
                                TextEntry::make('email_verified_at')
                                    ->label('Verificado el')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CalendarDays)
                                    ->visible(fn (User $record): bool => filled($record->email_verified_at)),
                                IconEntry::make('two_factor_enabled')
                                    ->label('Autenticación en dos pasos (2FA)')
                                    ->boolean()
                                    ->getStateUsing(fn (User $record): bool => filled($record->two_factor_confirmed_at))
                                    ->trueIcon(Heroicon::LockClosed)
                                    ->falseIcon(Heroicon::LockOpen)
                                    ->trueColor('success')
                                    ->falseColor('gray')
                                    ->tooltip(fn (User $record): string => filled($record->two_factor_confirmed_at)
                                        ? '2FA activa. El usuario configura códigos desde su perfil.'
                                        : '2FA no activada.'),
                                TextEntry::make('two_factor_confirmed_at')
                                    ->label('2FA confirmada el')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Clock)
                                    ->visible(fn (User $record): bool => filled($record->two_factor_confirmed_at)),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Registro de alta y última modificación en base de datos.')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::ArrowPathRoundedSquare),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }
}
