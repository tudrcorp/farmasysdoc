<?php

namespace App\Filament\Resources\AccountsPayables\Actions;

use App\Models\AccountsPayable;
use App\Support\Finance\AccountsPayableStatus;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

final class DownloadAccountsPayablePaymentReportAction
{
    public const NAME = 'downloadPaymentReport';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Reporte de pago PDF')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->url(function (Action $action): ?string {
                $record = $action->getRecord();
                if (! $record instanceof AccountsPayable) {
                    return null;
                }

                return URL::temporarySignedRoute(
                    'accounts-payables.payment-report-pdf',
                    now()->addMinutes(30),
                    ['accountsPayable' => $record->getKey()],
                );
            })
            ->openUrlInNewTab()
            ->visible(function (Action $action): bool {
                $record = $action->getRecord();

                return $record instanceof AccountsPayable
                    && $record->status === AccountsPayableStatus::PAGADO;
            });
    }
}
