<?php

namespace App\Filament\Resources\FiscalPrinters\Actions;

use App\Enums\FiscalDocumentType;
use App\Enums\FiscalPrinterMode;
use App\Models\FiscalPrinter;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class RequestFiscalReportAction
{
    public static function make(FiscalDocumentType $type): Action
    {
        $isZ = $type === FiscalDocumentType::ZReport;

        return Action::make($isZ ? 'requestZReport' : 'requestXReport')
            ->label($isZ ? 'Imprimir reporte Z' : 'Imprimir reporte X')
            ->icon($isZ ? Heroicon::LockClosed : Heroicon::DocumentText)
            ->color($isZ ? 'danger' : 'gray')
            ->requiresConfirmation()
            ->modalHeading($isZ ? '¿Imprimir el reporte Z (cierre fiscal del día)?' : '¿Imprimir el reporte X?')
            ->modalDescription($isZ
                ? 'El reporte Z cierra la jornada fiscal de esta máquina y no se puede deshacer. Hágalo solo al cierre del día.'
                : 'El reporte X es informativo y no cierra la jornada fiscal.')
            ->modalSubmitActionLabel($isZ ? 'Sí, imprimir Z' : 'Imprimir X')
            ->visible(fn (FiscalPrinter $record): bool => $record->is_active && $record->currentMode() === FiscalPrinterMode::Active)
            ->action(function (FiscalPrinter $record) use ($type): void {
                $user = Auth::user();

                app(FiscalDocumentRegistrar::class)->requestReport(
                    $record,
                    $type,
                    (string) ($user?->email ?? $user?->name ?? 'sistema'),
                );

                Notification::make()
                    ->title($type->label().' enviado a la cola')
                    ->body($record->isOnline()
                        ? 'La máquina fiscal lo imprimirá en unos segundos.'
                        : 'El agente de esta máquina no está en línea; se imprimirá cuando se conecte.')
                    ->success()
                    ->send();
            });
    }
}
