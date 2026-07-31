<?php

namespace App\Filament\Resources\InventoryAudits\Tables;

use App\Enums\InventoryAuditStatus;
use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Models\InventoryAudit;
use App\Services\Inventory\InventoryAuditApplyService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InventoryAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (InventoryAuditStatus|string|null $state): string => $state instanceof InventoryAuditStatus
                        ? $state->label()
                        : (InventoryAuditStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (InventoryAuditStatus|string|null $state): string => $state instanceof InventoryAuditStatus
                        ? $state->filamentColor()
                        : (InventoryAuditStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                TextColumn::make('progress')
                    ->label('Progreso')
                    ->state(function (InventoryAudit $record): string {
                        $p = $record->progressCounts();

                        return $p['processed'].'/'.$p['total'];
                    }),
                TextColumn::make('startedBy.name')
                    ->label('Iniciada por')
                    ->toggleable(),
                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(InventoryAuditStatus::options()),
            ])
            ->recordActions([
                Action::make('work')
                    ->label('Trabajar')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->url(fn (InventoryAudit $record): string => InventoryAuditResource::getUrl('work', ['record' => $record])),
                ViewAction::make()
                    ->label('Detalle'),
                Action::make('close')
                    ->label('Cerrar')
                    ->icon(Heroicon::LockClosed)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar auditoría')
                    ->modalDescription('Solo se puede cerrar si no quedan productos pendientes.')
                    ->visible(fn (InventoryAudit $record): bool => $record->isOpen())
                    ->action(function (InventoryAudit $record): void {
                        try {
                            app(InventoryAuditApplyService::class)->close($record, Auth::user());
                            Notification::make()
                                ->title('Auditoría cerrada')
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo cerrar')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
