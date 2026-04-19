<?php

namespace App\Filament\Resources\Rols\Schemas;

use App\Models\Rol;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RolInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rol')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre'),
                                TextEntry::make('description')
                                    ->label('Descripción')
                                    ->placeholder('—'),
                                IconEntry::make('is_active')
                                    ->label('Activo')
                                    ->boolean(),
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Ítems de menú permitidos')
                    ->icon(Heroicon::SquaresPlus)
                    ->schema([
                        TextEntry::make('allowed_menu_items_summary')
                            ->label('Permisos')
                            ->getStateUsing(function (Rol $record): string {
                                $allowed = $record->allowed_menu_items;

                                if (! is_array($allowed) || $allowed === []) {
                                    return 'Sin permisos específicos';
                                }

                                return collect($allowed)
                                    ->map(fn (string $key): string => FarmaadminMenuAccessCatalog::items()[$key]['label'] ?? $key)
                                    ->sort()
                                    ->implode(' · ');
                            })
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
