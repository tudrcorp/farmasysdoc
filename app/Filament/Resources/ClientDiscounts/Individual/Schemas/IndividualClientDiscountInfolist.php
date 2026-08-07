<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Schemas;

use App\Models\Client;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class IndividualClientDiscountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Descuento individual')
                    ->description('Porcentaje exclusivo de este cliente. No puede pertenecer a un grupo de descuento a la vez.')
                    ->icon(Heroicon::ReceiptPercent)
                    ->iconColor('primary')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('customer_discount')
                                    ->label('Descuento en caja')
                                    ->badge()
                                    ->color('success')
                                    ->size(TextSize::Large)
                                    ->icon(Heroicon::ReceiptPercent)
                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state).'%')
                                    ->helperText('Se aplica sobre el subtotal de toda la venta'),
                                TextEntry::make('discount_scope')
                                    ->label('Alcance')
                                    ->state('Toda la venta')
                                    ->badge()
                                    ->color('info')
                                    ->icon(Heroicon::ShoppingCart),
                                TextEntry::make('discount_mode')
                                    ->label('Modalidad')
                                    ->state('Individual')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::User),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Cliente')
                    ->description('Datos de identificación y contacto del beneficiario del descuento.')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre completo o razón social')
                            ->weight(FontWeight::SemiBold)
                            ->size(TextSize::Large)
                            ->icon(Heroicon::User)
                            ->iconColor('primary')
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('document_type')
                                    ->label('Tipo de documento')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'CC' => 'Cédula de Identidad',
                                        'CE' => 'Cédula Extranjero',
                                        'RIF' => 'RIF',
                                        'NIT' => 'NIT',
                                        'PAS' => 'Pasaporte',
                                        default => filled($state) ? (string) $state : '—',
                                    })
                                    ->icon(Heroicon::Identification),
                                TextEntry::make('document_number')
                                    ->label('Número de documento')
                                    ->badge()
                                    ->color('gray')
                                    ->copyable()
                                    ->copyMessage('Documento copiado')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Hashtag),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('email')
                                    ->label('Correo')
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
                            ]),
                        TextEntry::make('status')
                            ->label('Estado del cliente')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'active' => 'Activo',
                                'inactive' => 'Inactivo',
                                'blocked' => 'Bloqueado',
                                default => filled($state) ? (string) $state : '—',
                            })
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

                Section::make('Cómo se aplica en caja')
                    ->description('Resumen operativo para el cajero.')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('warning')
                    ->schema([
                        TextEntry::make('pos_hint')
                            ->hiddenLabel()
                            ->state(fn (Client $record): string => 'Al seleccionar a «'.$record->name.'» en caja, el sistema muestra el precio original, resta '
                                .self::formatPercent($record->customer_discount).'% sobre el subtotal y registra la venta con el descuento aplicado.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Historial del registro del cliente.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ArrowPath),
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function formatPercent(mixed $state): string
    {
        if ($state === null || $state === '' || ! is_numeric($state)) {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',');
    }
}
