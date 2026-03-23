<?php

namespace App\Filament\Resources\Sales\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('sale_number'),
                TextEntry::make('branch.name')
                    ->label('Branch'),
                TextEntry::make('client.name')
                    ->label('Client')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('tax_total')
                    ->numeric(),
                TextEntry::make('discount_total')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('payment_method')
                    ->placeholder('-'),
                TextEntry::make('payment_status')
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('sold_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->placeholder('-'),
                TextEntry::make('updated_by')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
