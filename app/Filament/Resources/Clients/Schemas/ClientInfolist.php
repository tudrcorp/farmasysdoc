<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use App\Models\User;
use App\Services\Marketing\MarketingAnalyticsService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidad y documento')
                    ->description('Datos de identificación del cliente o empresa.')
                    ->icon(Heroicon::User)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre completo o razón social')
                            ->icon(Heroicon::UserCircle),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('document_type')
                                    ->label('Tipo de documento')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'CC' => 'Cédula de ciudadanía',
                                        'CE' => 'Cédula de extranjería',
                                        'NIT' => 'NIT',
                                        'PAS' => 'Pasaporte',
                                        'TI' => 'Tarjeta de identidad',
                                        'RUT' => 'RUT',
                                        'OTRO' => 'Otro',
                                        default => $state ?? '—',
                                    })
                                    ->badge()
                                    ->color('gray')
                                    ->icon(Heroicon::Identification),
                                TextEntry::make('document_number')
                                    ->label('Número de documento')
                                    ->icon(Heroicon::Hashtag)
                                    ->copyable(),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Contacto')
                    ->description('Medios de comunicación y notificaciones.')
                    ->icon(Heroicon::Phone)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->icon(Heroicon::Envelope)
                                    ->copyable(),
                                TextEntry::make('phone')
                                    ->label('Teléfono principal')
                                    ->icon(Heroicon::DevicePhoneMobile),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->description('Dirección registrada.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextEntry::make('address')
                            ->label('Dirección')
                            ->placeholder('—')
                            ->icon(Heroicon::Home)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->icon(Heroicon::BuildingLibrary),
                                TextEntry::make('state')
                                    ->label('Departamento / estado')
                                    ->icon(Heroicon::Map),
                                TextEntry::make('country')
                                    ->label('País')
                                    ->icon(Heroicon::GlobeAlt),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Comportamiento (marketing)')
                    ->description('Métricas basadas en ventas con estado «completada».')
                    ->icon(Heroicon::ChartBar)
                    ->extraAttributes(['class' => 'fi-client-marketing-behavior'])
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->canAccessMarketingModule())
                    ->schema([
                        TextEntry::make('id')
                            ->hiddenLabel()
                            ->formatStateUsing(function (Client $record): string {
                                $m = app(MarketingAnalyticsService::class)->clientBehaviorMetrics($record);

                                return view('filament.clients.marketing-metrics', ['m' => $m])->render();
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Estado del cliente')
                    ->description('Condición comercial en el sistema.')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'active' => 'Activo',
                                'inactive' => 'Inactivo',
                                'blocked' => 'Bloqueado',
                                default => $state ?? '—',
                            })
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'gray',
                                'blocked' => 'danger',
                                default => 'gray',
                            })
                            ->icon(Heroicon::Signal),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Historial del registro.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('updated_by')
                                    ->label('Última modificación por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
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
