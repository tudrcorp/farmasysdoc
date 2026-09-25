<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Purchases\PurchaseBcvRate;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListAccountsPayables extends ListRecords
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';

    public ?string $lastSyncedBcvRateLabel = null;

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->lastSyncedBcvRateLabel)) {
            return 'Tasa BCV actual (última sincronización): '.$this->lastSyncedBcvRateLabel.' Bs/USD.';
        }

        $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now());

        if ($rate === null || $rate <= 0) {
            return 'Tasa BCV actual: no disponible.';
        }

        return 'Tasa BCV actual: '.PurchaseBcvRate::format($rate).' Bs/USD.';
    }
}
