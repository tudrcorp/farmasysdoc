<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Models\ProductCategory;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidad')
                    ->description('Nombre, descripción e imagen de la categoría.')
                    ->icon(Heroicon::Tag)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 3,
                        ])
                            ->schema([
                                Grid::make(1)
                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Nombre')
                                            ->icon(Heroicon::Tag)
                                            ->weight('medium')
                                            ->placeholder('—'),
                                        TextEntry::make('description')
                                            ->label('Descripción')
                                            ->icon(Heroicon::DocumentText)
                                            ->placeholder('Sin descripción')
                                            ->columnSpanFull(),
                                        TextEntry::make('slug')
                                            ->label('Slug')
                                            ->icon(Heroicon::Link)
                                            ->copyable()
                                            ->copyMessage('Slug copiado')
                                            ->placeholder('—'),
                                    ]),
                                ImageEntry::make('image')
                                    ->label('Imagen')
                                    ->disk('public')
                                    ->height(160)
                                    ->placeholder('Sin imagen')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Comercial')
                    ->description('Estado y parámetros de margen.')
                    ->icon(Heroicon::ChartBar)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Estado')
                                    ->boolean()
                                    ->trueIcon(Heroicon::CheckCircle)
                                    ->falseIcon(Heroicon::XCircle)
                                    ->trueColor('success')
                                    ->falseColor('danger'),
                                IconEntry::make('is_medication')
                                    ->label('Medicamentos')
                                    ->boolean()
                                    ->trueIcon(Heroicon::Beaker)
                                    ->falseIcon(Heroicon::MinusSmall)
                                    ->trueColor('danger')
                                    ->falseColor('gray'),
                                TextEntry::make('profit_percentage')
                                    ->label('Margen (%)')
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix('%')
                                    ->icon(Heroicon::Calculator)
                                    ->placeholder('0'),
                                TextEntry::make('products_count')
                                    ->label('Productos en categoría')
                                    ->icon(Heroicon::Cube)
                                    ->getStateUsing(fn (ProductCategory $record): int => (int) $record->products()->count()),
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
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->icon(Heroicon::UserCircle)
                                    ->formatStateUsing(fn (?string $state): ?string => self::formatUserName($state))
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->icon(Heroicon::ArrowPath)
                                    ->formatStateUsing(fn (?string $state): ?string => self::formatUserName($state))
                                    ->placeholder('—'),
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

    private static function formatUserName(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        $user = User::query()->find($state);

        return $user?->name ?? $state;
    }
}
