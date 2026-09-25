<?php

namespace App\Filament\Resources\MedicationQuotes\Schemas;

use App\Enums\MedicationQuoteAdjustment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MedicationQuoteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitante')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('number')->label('Cotización'),
                        TextEntry::make('requester_name')->label('Nombre o razón social'),
                        TextEntry::make('requester_document')->label('Cédula o RIF'),
                        TextEntry::make('requester_phone')->label('Teléfono'),
                        TextEntry::make('requester_email')->label('Correo')->columnSpanFull(),
                    ]),
                Section::make('Ajuste interno')
                    ->description('Este ajuste no se imprime. El PDF solo muestra el total final.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('adjustment_kind')
                            ->label('Tipo')
                            ->formatStateUsing(fn (MedicationQuoteAdjustment|string|null $state): string => $state instanceof MedicationQuoteAdjustment ? $state->label() : MedicationQuoteAdjustment::tryLabel($state)),
                        TextEntry::make('adjustment_percent')
                            ->label('Porcentaje')
                            ->suffix('%'),
                        TextEntry::make('subtotal_usd')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.')),
                        TextEntry::make('total_usd')
                            ->label('Total final')
                            ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.'))
                            ->columnSpanFull(),
                    ]),
                RepeatableEntry::make('lines')
                    ->label('Medicamentos')
                    ->schema([
                        TextEntry::make('description')->label('Medicamento'),
                        TextEntry::make('quantity')->label('Cantidad'),
                        TextEntry::make('unit_price_usd')
                            ->label('Precio')
                            ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.')),
                        TextEntry::make('line_total_usd')
                            ->label('Importe')
                            ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.')),
                    ])
                    ->columns(4),
                TextEntry::make('notes')->label('Observaciones')->placeholder('—')->columnSpanFull(),
            ]);
    }
}
