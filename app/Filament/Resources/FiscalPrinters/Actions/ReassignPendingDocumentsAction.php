<?php

namespace App\Filament\Resources\FiscalPrinters\Actions;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalPrinterMode;
use App\Models\FiscalPrinter;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReassignPendingDocumentsAction
{
    public static function make(): Action
    {
        return Action::make('reassignPendingDocuments')
            ->label('Reasignar pendientes')
            ->icon(Heroicon::ArrowsRightLeft)
            ->color('warning')
            ->visible(fn (FiscalPrinter $record): bool => $record->documents()
                ->where('status', FiscalDocumentStatus::Pending)
                ->where('simulation', false)
                ->where('is_test', false)
                ->exists())
            ->modalHeading('Reasignar documentos pendientes')
            ->modalDescription('Úselo si esta máquina se dañó: las facturas que aún no tomó el agente se imprimirán en otra máquina activa de la sucursal.')
            ->schema([
                Select::make('target_id')
                    ->label('Máquina destino')
                    ->options(fn (FiscalPrinter $record): array => FiscalPrinter::query()
                        ->whereKeyNot($record->getKey())
                        ->where('branch_id', $record->branch_id)
                        ->where('is_active', true)
                        ->where('mode', FiscalPrinterMode::Active)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (FiscalPrinter $record, array $data): void {
                $user = Auth::user();

                try {
                    $moved = app(FiscalDocumentRegistrar::class)->reassignPending(
                        $record,
                        FiscalPrinter::query()->findOrFail($data['target_id']),
                        (string) ($user?->email ?? $user?->name ?? 'sistema'),
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('No se pudo reasignar')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($moved.' documento(s) reasignados')
                    ->success()
                    ->send();
            });
    }
}
