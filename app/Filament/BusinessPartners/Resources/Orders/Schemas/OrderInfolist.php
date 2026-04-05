<?php

namespace App\Filament\BusinessPartners\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Schemas\OrderInfolist as SharedOrderInfolist;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedOrderInfolist::configure($schema, enableAdminResourceLinks: false);
    }
}
