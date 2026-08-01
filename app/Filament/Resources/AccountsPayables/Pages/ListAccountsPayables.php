<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Services\Finance\AccountsPayableCurrentBalanceRecalculator;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
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

        return 'Tasa BCV actual: '.number_format($rate, 4, ',', '.').' Bs/USD.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCurrentBalancesBcv')
                ->label('Sincronizar saldos BCV')
                ->icon(Heroicon::ArrowPath)
                ->color('primary')
                ->requiresConfirmation()
                ->modalIcon(Heroicon::ArrowPath)
                ->modalHeading('Sincronizar saldos al día')
                ->modalDescription(function (): string {
                    $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now());
                    $rateLabel = ($rate !== null && $rate > 0)
                        ? number_format($rate, 4, ',', '.').' Bs/USD'
                        : 'no disponible';

                    return 'Recalculará el saldo al día de las cuentas «Por pagar» visibles con los filtros actuales: '
                        .'total a pagar (Bs) ÷ tasa BCV del registro de la compra × tasa BCV oficial de hoy.'
                        .' Tasa BCV actual: '.$rateLabel.'.';
                })
                ->modalSubmitActionLabel('Sincronizar')
                ->action(function (): void {
                    $query = $this->getFilteredTableQuery()
                        ?? AccountsPayableResource::getEloquentQuery();

                    $result = app(AccountsPayableCurrentBalanceRecalculator::class)
                        ->recalculateMany($query->clone());

                    if (! $result['ok']) {
                        Notification::make()
                            ->title('No se pudo sincronizar')
                            ->body((string) $result['error'])
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->lastSyncedBcvRateLabel = number_format((float) $result['rate'], 4, ',', '.');

                    Notification::make()
                        ->title('Saldos sincronizados')
                        ->body(
                            'Procesadas: '.$result['processed']
                            .' · Con cambio de saldo: '.$result['changed']
                            .' · Tasa BCV actual: '.$this->lastSyncedBcvRateLabel.' Bs/USD'
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}
