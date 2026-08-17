<?php

namespace App\Filament\Resources\PosTerminals\Schemas;

use App\Models\PosTerminal;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PosTerminalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Punto de venta')
                    ->description('Terminal bancario asociado a una sucursal.')
                    ->icon(Heroicon::CreditCard)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Código del punto')
                                    ->copyable()
                                    ->copyMessage('Código copiado')
                                    ->icon(Heroicon::Hashtag)
                                    ->placeholder('—'),
                                TextEntry::make('bank_code')
                                    ->label('Banco')
                                    ->state(fn (PosTerminal $record): string => $record->bankLabel())
                                    ->icon(Heroicon::BuildingLibrary)
                                    ->placeholder('—'),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->placeholder('—'),
                                IconEntry::make('is_active')
                                    ->label('Activo')
                                    ->boolean()
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Registro de altas y últimas modificaciones.')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::CalendarDays)
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última edición')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::Clock)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
