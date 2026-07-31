<?php

namespace App\Filament\Resources\InventoryAudits\Pages;

use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Models\InventoryAudit;
use App\Services\Inventory\InventoryAuditApplyService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewInventoryAudit extends ViewRecord
{
    protected static string $resource = InventoryAuditResource::class;

    protected static ?string $title = 'Detalle de auditoría';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('work')
                ->label('Trabajar productos')
                ->icon(Heroicon::ClipboardDocumentList)
                ->url(fn (): string => InventoryAuditResource::getUrl('work', ['record' => $this->getRecord()])),
            Action::make('close')
                ->label('Cerrar auditoría')
                ->icon(Heroicon::LockClosed)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord() instanceof InventoryAudit && $this->getRecord()->isOpen())
                ->action(function (): void {
                    $record = $this->getRecord();
                    if (! $record instanceof InventoryAudit) {
                        return;
                    }

                    try {
                        app(InventoryAuditApplyService::class)->close($record, Auth::user());
                        Notification::make()->title('Auditoría cerrada')->success()->send();
                        $this->record->refresh();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('No se pudo cerrar')
                            ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
