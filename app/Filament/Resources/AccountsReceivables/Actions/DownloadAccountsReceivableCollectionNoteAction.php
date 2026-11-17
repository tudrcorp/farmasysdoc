<?php

namespace App\Filament\Resources\AccountsReceivables\Actions;

use App\Enums\SaleStatus;
use App\Models\AccountsReceivable;
use App\Support\Finance\AccountsReceivableStatus;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

final class DownloadAccountsReceivableCollectionNoteAction
{
    public const NAME = 'downloadCollectionNote';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Nota de cobro')
            ->icon(Heroicon::DocumentText)
            ->color('gray')
            ->url(function (Action $action): ?string {
                $record = $action->getRecord();
                if (! $record instanceof AccountsReceivable) {
                    return null;
                }

                return URL::temporarySignedRoute(
                    'accounts-receivables.collection-note-pdf',
                    now()->addMinutes(30),
                    ['accountsReceivable' => $record->getKey()],
                );
            })
            ->openUrlInNewTab()
            ->visible(function (Action $action): bool {
                $record = $action->getRecord();

                return $record instanceof AccountsReceivable && self::isAvailableFor($record);
            });
    }

    /**
     * Oculta la nota de cobro cuando la cuenta o su venta fueron anuladas.
     */
    public static function isAvailableFor(AccountsReceivable $record): bool
    {
        if ($record->status === AccountsReceivableStatus::CANCELADO) {
            return false;
        }

        $saleStatus = $record->sale?->status;

        return ! in_array($saleStatus, [SaleStatus::Cancelled, SaleStatus::Refunded], true);
    }
}
