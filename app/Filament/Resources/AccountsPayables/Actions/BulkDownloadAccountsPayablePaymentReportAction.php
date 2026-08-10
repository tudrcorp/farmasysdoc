<?php

namespace App\Filament\Resources\AccountsPayables\Actions;

use App\Models\AccountsPayable;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Livewire\Component;

final class BulkDownloadAccountsPayablePaymentReportAction
{
    public const NAME = 'bulkDownloadPaymentReport';

    public static function make(): BulkAction
    {
        return BulkAction::make(self::NAME)
            ->label('Reporte PDF (pagadas)')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->before(function (Collection $records): void {
                if ($records->isEmpty()) {
                    Notification::make()
                        ->title('Sin selección')
                        ->body('Seleccione al menos una cuenta por pagar pagada.')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                $notPaid = $records->first(
                    static fn (mixed $record): bool => ! $record instanceof AccountsPayable
                        || $record->status !== AccountsPayableStatus::PAGADO,
                );

                if ($notPaid !== null) {
                    Notification::make()
                        ->title('Selección inválida')
                        ->body('El reporte PDF masivo solo admite cuentas en estado «Pagado». Quite las que estén por pagar o anuladas.')
                        ->danger()
                        ->send();

                    throw new Halt;
                }

                if ($records->count() > 100) {
                    Notification::make()
                        ->title('Demasiados registros')
                        ->body('Puede incluir como máximo 100 cuentas por pagar en un mismo PDF.')
                        ->danger()
                        ->send();

                    throw new Halt;
                }
            })
            ->action(function (Collection $records, Component $livewire): void {
                $ids = $records
                    ->filter(static fn (mixed $record): bool => $record instanceof AccountsPayable)
                    ->map(static fn (AccountsPayable $record): int => (int) $record->getKey())
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                AuditLogger::record(
                    event: 'filament_accounts_payable_bulk_payment_report_requested',
                    description: 'CxP: el usuario solicitó el PDF masivo de pagos.',
                    properties: [
                        'accounts_payable_ids' => $ids,
                        'count' => count($ids),
                    ],
                );

                $url = URL::temporarySignedRoute(
                    'accounts-payables.bulk-payment-report-pdf',
                    now()->addMinutes(30),
                    ['ids' => implode(',', $ids)],
                );

                $livewire->js('window.open('.Js::from($url).', "_blank")');

                Notification::make()
                    ->title('Descarga iniciada')
                    ->body('Se abrió una pestaña con el PDF de '.count($ids).' cuenta(s) pagada(s). Si no aparece, permita ventanas emergentes.')
                    ->success()
                    ->send();
            });
    }
}
