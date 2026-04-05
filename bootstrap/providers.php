<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\BusinessPartnersPanelProvider;
use App\Providers\Filament\FarmaadminPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    BusinessPartnersPanelProvider::class,
    FarmaadminPanelProvider::class,
    FortifyServiceProvider::class,
];
