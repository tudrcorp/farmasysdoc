<?php

namespace App\Filament\Resources\AccountsPayables\Pages;

use App\Filament\Resources\AccountsPayables\AccountsPayableResource;
use App\Services\Finance\AccountsPayableCurrentBalanceRecalculator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAccountsPayables extends ListRecords
{
    protected static string $resource = AccountsPayableResource::class;

    protected static ?string $title = 'Cuentas por pagar';

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
                ->modalDescription(
                    'Recalculará el saldo en bolívares de todas las cuentas «Por pagar» visibles con los filtros actuales, usando la tasa BCV oficial de hoy (principal pendiente USD × tasa).'
                )
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

                    Notification::make()
                        ->title('Saldos sincronizados')
                        ->body(
                            'Procesadas: '.$result['processed']
                            .' · Con cambio de saldo: '.$result['changed']
                            .' · Tasa BCV: '.number_format((float) $result['rate'], 4, ',', '.')
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}
