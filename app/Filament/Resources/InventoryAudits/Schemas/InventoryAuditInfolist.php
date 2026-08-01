<?php

namespace App\Filament\Resources\InventoryAudits\Schemas;

use App\Enums\InventoryAuditStatus;
use App\Models\InventoryAudit;
use App\Support\Inventory\InventoryAuditLetterRange;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryAuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sesión')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('branch.name')
                            ->label('Sucursal'),
                        TextEntry::make('productCategory.name')
                            ->label('Categoría')
                            ->placeholder('Todas las categorías'),
                        TextEntry::make('letter_range')
                            ->label('Rango alfabético')
                            ->state(fn (InventoryAudit $record): string => InventoryAuditLetterRange::label(
                                $record->letter_from,
                                $record->letter_to,
                            ) ?? 'A – Z (todos)'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (InventoryAuditStatus|string|null $state): string => $state instanceof InventoryAuditStatus
                                ? $state->label()
                                : (InventoryAuditStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                            ->color(fn (InventoryAuditStatus|string|null $state): string => $state instanceof InventoryAuditStatus
                                ? $state->filamentColor()
                                : (InventoryAuditStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                        TextEntry::make('progress')
                            ->label('Progreso')
                            ->state(function (InventoryAudit $record): string {
                                $p = $record->progressCounts();

                                return $p['processed'].' / '.$p['total'].' procesados'
                                    .' ('.$p['verified'].' sin cambios, '.$p['updated'].' actualizados, '.$p['pending'].' pendientes)';
                            }),
                        TextEntry::make('startedBy.name')
                            ->label('Iniciada por')
                            ->placeholder('—'),
                        TextEntry::make('started_at')
                            ->label('Inicio')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('closedBy.name')
                            ->label('Cerrada por')
                            ->placeholder('—'),
                        TextEntry::make('closed_at')
                            ->label('Cierre')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
