<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Schemas;

use App\Enums\TrainingModality;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TrainingSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Capacitación')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextEntry::make('course.name')->label('Curso'),
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(fn (TrainingSessionStatus $state): string => $state->label())
                                ->color(fn (TrainingSessionStatus $state): string => $state->filamentColor()),
                            TextEntry::make('branch.name')->label('Sucursal')->placeholder('General'),
                            TextEntry::make('schedule')->label('Fecha')->state(fn (TrainingSession $record): string => $record->scheduleLabel()),
                            TextEntry::make('modality')->label('Modalidad')->formatStateUsing(fn (TrainingModality $state): string => $state->label()),
                            TextEntry::make('place')
                                ->label('Lugar o enlace')
                                ->state(fn (TrainingSession $record): ?string => $record->location ?? $record->meeting_url)
                                ->placeholder('—'),
                            TextEntry::make('facilitator')
                                ->label('Facilitador')
                                ->state(fn (TrainingSession $record): ?string => $record->facilitatorLabel())
                                ->placeholder('—'),
                            TextEntry::make('occupancy')
                                ->label('Inscritos')
                                ->state(fn (TrainingSession $record): string => $record->enrollments()->count().($record->capacity !== null ? " de {$record->capacity}" : '')),
                            TextEntry::make('cost')
                                ->label('Costo')
                                ->state(fn (TrainingSession $record): ?string => self::costLabel($record))
                                ->placeholder('—'),
                        ]),
                        TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function costLabel(TrainingSession $session): ?string
    {
        $parts = array_filter([
            $session->cost_usd !== null ? 'US$ '.number_format((float) $session->cost_usd, 2, ',', '.') : null,
            $session->cost_ves !== null ? 'Bs '.number_format((float) $session->cost_ves, 2, ',', '.') : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
