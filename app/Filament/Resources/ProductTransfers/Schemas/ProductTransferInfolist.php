<?php

namespace App\Filament\Resources\ProductTransfers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('product.name')
                    ->label('Product'),
                TextEntry::make('from_branch_id')
                    ->numeric(),
                TextEntry::make('to_branch_id')
                    ->numeric(),
                TextEntry::make('quantity')
                    ->numeric(),
                TextEntry::make('status'),
                TextEntry::make('transfer_type'),
                TextEntry::make('created_by'),
                TextEntry::make('updated_by'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
