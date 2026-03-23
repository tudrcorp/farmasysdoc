<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('order_number'),
                TextEntry::make('client.name')
                    ->label('Client'),
                TextEntry::make('branch.name')
                    ->label('Branch')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('convenio_type')
                    ->badge(),
                TextEntry::make('convenio_partner_name')
                    ->placeholder('-'),
                TextEntry::make('convenio_reference')
                    ->placeholder('-'),
                TextEntry::make('convenio_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('delivery_recipient_name')
                    ->placeholder('-'),
                TextEntry::make('delivery_phone')
                    ->placeholder('-'),
                TextEntry::make('delivery_address')
                    ->placeholder('-'),
                TextEntry::make('delivery_city')
                    ->placeholder('-'),
                TextEntry::make('delivery_state')
                    ->placeholder('-'),
                TextEntry::make('delivery_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('scheduled_delivery_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('dispatched_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('delivered_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('delivery_assignee')
                    ->placeholder('-'),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('tax_total')
                    ->numeric(),
                TextEntry::make('discount_total')
                    ->numeric(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
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
