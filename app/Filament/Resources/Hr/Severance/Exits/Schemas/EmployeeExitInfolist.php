<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Schemas;

use App\Enums\EmployeeExitChecklistItem;
use App\Enums\EmployeeExitReason;
use App\Enums\EmployeeExitStatus;
use App\Models\EmployeeExit;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmployeeExitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Egreso')
                    ->icon(Heroicon::ArrowRightStartOnRectangle)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextEntry::make('employee_name')->label('Empleado')->state(fn (EmployeeExit $record): string => $record->employee->fullName()),
                            TextEntry::make('employee.branch.name')->label('Sucursal')->badge()->color('info'),
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(fn (EmployeeExitStatus $state): string => $state->label())
                                ->color(fn (EmployeeExitStatus $state): string => $state->filamentColor()),
                            TextEntry::make('reason')->label('Motivo')->formatStateUsing(fn (EmployeeExitReason $state): string => $state->label()),
                            TextEntry::make('last_work_day')->label('Último día')->date('d/m/Y'),
                            TextEntry::make('document')
                                ->label('Soporte')
                                ->state(fn (EmployeeExit $record): ?string => filled($record->document_path) ? 'Abrir soporte' : null)
                                ->url(fn (EmployeeExit $record): ?string => filled($record->document_path) ? route('severance.exit-document', $record) : null, shouldOpenInNewTab: true)
                                ->color('primary')
                                ->placeholder('Sin soporte'),
                        ]),
                        TextEntry::make('notes')->label('Observaciones')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('cancel_reason')->label('Motivo de cancelación')->visible(fn (EmployeeExit $record): bool => filled($record->cancel_reason))->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Lista de salida')
                    ->description('Debe estar completa para poder pagar la liquidación.')
                    ->icon(Heroicon::ClipboardDocumentCheck)
                    ->schema([
                        TextEntry::make('checklist_status')
                            ->hiddenLabel()
                            ->state(fn (EmployeeExit $record): array => array_map(
                                fn (EmployeeExitChecklistItem $item): string => (in_array($item->value, $record->checklist ?? [], true) ? '✓ ' : '○ ').$item->label(),
                                EmployeeExitChecklistItem::cases(),
                            ))
                            ->badge()
                            ->color(fn (string $state): string => str_starts_with($state, '✓') ? 'success' : 'gray'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
