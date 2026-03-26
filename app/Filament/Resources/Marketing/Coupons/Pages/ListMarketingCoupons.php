<?php

namespace App\Filament\Resources\Marketing\Coupons\Pages;

use App\Filament\Resources\Marketing\Coupons\MarketingCouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketingCoupons extends ListRecords
{
    protected static string $resource = MarketingCouponResource::class;

    protected static ?string $title = 'Cupones y descuentos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo cupón')
                ->icon(Heroicon::Plus),
        ];
    }
}
