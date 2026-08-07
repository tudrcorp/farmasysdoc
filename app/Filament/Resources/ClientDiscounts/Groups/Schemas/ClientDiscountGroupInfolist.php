<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Schemas;

use App\Models\Client;
use App\Models\ClientDiscountGroup;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ClientDiscountGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen del grupo')
                    ->description('Identificación y porcentaje que se aplica en caja a todos los clientes asociados.')
                    ->icon(Heroicon::UserGroup)
                    ->iconColor('primary')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre del grupo')
                                    ->weight(FontWeight::SemiBold)
                                    ->size(TextSize::Large)
                                    ->icon(Heroicon::Tag)
                                    ->iconColor('primary')
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 2]),
                                TextEntry::make('discount_percent')
                                    ->label('Descuento')
                                    ->badge()
                                    ->color('success')
                                    ->icon(Heroicon::ReceiptPercent)
                                    ->formatStateUsing(fn ($state): string => self::formatPercent($state).'%')
                                    ->helperText('Sobre el subtotal de toda la venta'),
                                TextEntry::make('is_active')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'Activo' : 'Inactivo')
                                    ->color(fn ($state): string => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'success' : 'gray')
                                    ->icon(fn ($state): Heroicon => filter_var($state, FILTER_VALIDATE_BOOLEAN)
                                        ? Heroicon::CheckCircle
                                        : Heroicon::PauseCircle)
                                    ->helperText('Si está inactivo, no aplica en caja'),
                            ]),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->icon(Heroicon::DocumentText)
                            ->iconColor('gray')
                            ->columnSpanFull()
                            ->visible(fn (ClientDiscountGroup $record): bool => filled($record->notes)),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Clientes del grupo')
                    ->description('Un cliente solo puede pertenecer a un grupo y no puede tener descuento individual a la vez.')
                    ->icon(Heroicon::Users)
                    ->iconColor('info')
                    ->schema([
                        TextEntry::make('clients_count')
                            ->label('Total asociados')
                            ->state(fn (ClientDiscountGroup $record): int => (int) ($record->clients_count ?? $record->clients()->count()))
                            ->badge()
                            ->color(fn (ClientDiscountGroup $record): string => ((int) ($record->clients_count ?? $record->clients()->count())) > 0 ? 'info' : 'gray')
                            ->icon(Heroicon::UserGroup)
                            ->formatStateUsing(fn ($state): string => ((int) $state) === 1
                                ? '1 cliente'
                                : ((int) $state).' clientes'),
                        RepeatableEntry::make('clients')
                            ->label('Listado')
                            ->placeholder('Este grupo aún no tiene clientes. Edítalo para asociar.')
                            ->table([
                                TableColumn::make('Cliente'),
                                TableColumn::make('Documento'),
                                TableColumn::make('Teléfono'),
                                TableColumn::make('Estado'),
                            ])
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Medium)
                                    ->icon(Heroicon::User)
                                    ->iconColor('gray'),
                                TextEntry::make('document_number')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?string $state, Client $record): string => filled($state)
                                        ? trim(($record->document_type ? $record->document_type.' ' : '').$state)
                                        : '—'),
                                TextEntry::make('phone')
                                    ->hiddenLabel()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Phone)
                                    ->iconColor('gray'),
                                TextEntry::make('status')
                                    ->hiddenLabel()
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
                                    }),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Historial del registro del grupo.')
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
