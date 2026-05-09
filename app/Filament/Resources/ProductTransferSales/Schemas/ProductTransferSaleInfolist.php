<?php

namespace App\Filament\Resources\ProductTransferSales\Schemas;

use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferInfolist;
use Filament\Schemas\Schema;

class ProductTransferSaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProductTransferInfolist::configure($schema);
    }
}
