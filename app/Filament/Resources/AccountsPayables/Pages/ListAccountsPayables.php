<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Finance\BcvRate;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class ListAccountsPayables extends ListRecords
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';

    public ?string $lastSyncedBcvRateLabel = null;

    /**
     * @var array<string, mixed>
     */
    public array $bcvSyncResult = [];

    public function syncBalanceResultAction(): Action
    {
        return Action::make('syncBalanceResult')
            ->modalHeading('Total a pagar sincronizado')
            ->modalIcon(Heroicon::ArrowPath)
            ->modalIconColor('primary')
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cerrar')
                ->color('gray'))
            ->modalContent(fn (): View => view('filament.accounts-payables.bcv-sync-result', [
                'result' => $this->bcvSyncResult,
            ]));
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->lastSyncedBcvRateLabel)) {
            return 'Tasa BCV actual (última sincronización): '.$this->lastSyncedBcvRateLabel.' Bs/USD.';
        }

        $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now());

        if ($rate === null || $rate <= 0) {
            return 'Tasa BCV actual: no disponible.';
        }

        return 'Tasa BCV actual: '.BcvRate::format($rate).' Bs/USD.';
    }
}
