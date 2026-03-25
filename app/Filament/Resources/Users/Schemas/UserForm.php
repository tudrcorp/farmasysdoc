<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Rol;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del usuario')
                    ->description('Identificación en el panel y vínculo con la sucursal donde opera.')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre completo')
                                    ->placeholder('Ej. María López')
                                    ->helperText('Como se mostrará en el panel y en notificaciones.')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::User)
                                    ->autocomplete('name')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->placeholder('correo@empresa.com')
                                    ->helperText('Se usa para iniciar sesión; debe ser único.')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->prefixIcon(Heroicon::Envelope)
                                    ->autocomplete('email')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                Select::make('roles')
                                    ->label('Roles')
                                    ->options(Rol::all()->pluck('name', 'name'))
                                    ->required()
                                    ->multiple()
                                    ->native(false),
                            ]),
                        Select::make('branch_id')
                            ->label('Sucursal')
                            ->placeholder('Seleccione la sucursal')
                            ->helperText('El usuario queda asociado a una sola sucursal activa.')
                            ->relationship(
                                name: 'branch',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->prefixIcon(Heroicon::BuildingOffice2)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contraseña')
                    ->description('Al crear es obligatoria. Al editar, deje en blanco para no cambiarla.')
                    ->icon(Heroicon::Key)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->revealable(filament()->arePasswordsRevealable())
                                    ->required(fn ($livewire): bool => $livewire instanceof CreateRecord)
                                    ->minLength(8)
                                    ->maxLength(255)
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->same('password_confirmation')
                                    ->validationAttribute('contraseña')
                                    ->helperText('Mínimo 8 caracteres. En edición solo rellene si desea reemplazar la actual.')
                                    ->autocomplete('new-password')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                TextInput::make('password_confirmation')
                                    ->label('Confirmar contraseña')
                                    ->password()
                                    ->revealable(filament()->arePasswordsRevealable())
                                    ->required(fn ($livewire, Get $get): bool => $livewire instanceof CreateRecord || filled($get('password')))
                                    ->dehydrated(false)
                                    ->autocomplete('new-password')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Verificación de correo')
                    ->description('Marca manualmente si el correo ya fue verificado (equivalente a «email verificado» en Laravel).')
                    ->icon(Heroicon::CheckBadge)
                    ->schema([
                        DateTimePicker::make('email_verified_at')
                            ->label('Verificado el')
                            ->helperText('Vacío = correo aún no verificado. Puede usar la fecha y hora en que validó el enlace.')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->prefixIcon(Heroicon::CalendarDays)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }
}
