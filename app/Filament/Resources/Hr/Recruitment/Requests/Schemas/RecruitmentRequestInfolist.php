<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Schemas;

use App\Enums\RecruitmentRequestPriority;
use App\Enums\RecruitmentRequestStatus;
use App\Models\RecruitmentRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RecruitmentRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vacante')
                    ->icon(Heroicon::Briefcase)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextEntry::make('job_title')->label('Cargo'),
                            TextEntry::make('branch.name')->label('Sucursal')->badge()->color('info'),
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->formatStateUsing(fn (RecruitmentRequestStatus $state): string => $state->label())
                                ->color(fn (RecruitmentRequestStatus $state): string => $state->filamentColor()),
                            TextEntry::make('coverage')
                                ->label('Vacantes cubiertas')
                                ->state(fn (RecruitmentRequest $record): string => "{$record->hiredCount()} de {$record->vacancies}"),
                            TextEntry::make('reason')->label('Motivo')->formatStateUsing(fn ($state): string => $state->label()),
                            TextEntry::make('priority')
                                ->label('Prioridad')
                                ->badge()
                                ->formatStateUsing(fn (RecruitmentRequestPriority $state): string => $state->label())
                                ->color(fn (RecruitmentRequestPriority $state): string => $state->filamentColor()),
                            TextEntry::make('needed_by')->label('Fecha requerida')->date('d/m/Y')->placeholder('—'),
                            TextEntry::make('reference_salary_usd')->label('Sueldo referencial')->money('USD')->placeholder('—'),
                            TextEntry::make('requester.name')->label('Solicitada por')->placeholder('—'),
                        ]),
                        TextEntry::make('requirements')->label('Requisitos del cargo')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Revisión')
                    ->icon(Heroicon::ShieldCheck)
                    ->visible(fn (RecruitmentRequest $record): bool => $record->reviewed_at !== null)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextEntry::make('reviewer.name')->label('Revisada por')->placeholder('—'),
                            TextEntry::make('reviewed_at')->label('Fecha')->dateTime('d/m/Y h:i a'),
                        ]),
                        TextEntry::make('review_note')->label('Nota')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
