<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Branches\BranchResource;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                                            ->map(fn (mixed $role): string => (string) $role)
                                            ->implode(', ');
                                    })
                                    ->placeholder('Sin roles asignados')
                                    ->visible(fn (User $record): bool => is_array($record->roles) && $record->roles !== []),
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
