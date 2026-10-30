<?php

namespace App\Filament\Resources\Hr\Recruitment\Applications\Schemas;

use App\Enums\RecruitmentStage;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentApplicationEvent;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RecruitmentApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proceso')
                    ->icon(Heroicon::ViewColumns)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            TextEntry::make('stage')
                                ->label('Etapa')
                                ->badge()
                                ->formatStateUsing(fn (RecruitmentStage $state): string => $state->label())
                                ->color(fn (RecruitmentStage $state): string => $state->filamentColor()),
                            TextEntry::make('recruitmentRequest.job_title')->label('Cargo'),
                            TextEntry::make('recruitmentRequest.branch.name')->label('Sucursal')->badge()->color('info'),
                            TextEntry::make('rating')->label('Calificación')->suffix(' / 5')->placeholder('—'),
                            TextEntry::make('interview_at')->label('Entrevista')->dateTime('d/m/Y h:i a')->placeholder('—'),
                            TextEntry::make('discard_reason')->label('Motivo de descarte')->placeholder('—'),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Candidato')
                    ->icon(Heroicon::User)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])->schema([
                            ImageEntry::make('candidate.photo_path')->label('Foto')->disk('public')->circular()->placeholder('Sin foto'),
                            TextEntry::make('candidate_name')->label('Nombre')->state(fn (RecruitmentApplication $record): string => $record->candidate->fullName()),
                            TextEntry::make('candidate.national_id')->label('Cédula')->copyable(),
                            TextEntry::make('candidate.phone')->label('Teléfono')->placeholder('—'),
                            TextEntry::make('candidate.email')->label('Email')->placeholder('—'),
                            TextEntry::make('candidate.years_of_experience')->label('Años de experiencia')->placeholder('—'),
                        ]),
                        TextEntry::make('candidate.address')->label('Dirección de habitación')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('candidate.education')->label('Estudios')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('candidate.work_experience')->label('Experiencia laboral')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('cv')
                            ->label('Curriculum vitae')
                            ->state('Abrir CV')
                            ->icon(Heroicon::DocumentText)
                            ->color('primary')
                            ->url(fn (RecruitmentApplication $record): string => route('recruitment.candidate-cv', $record->candidate), shouldOpenInNewTab: true)
                            ->visible(fn (RecruitmentApplication $record): bool => $record->candidate->hasCv()),
                    ])
                    ->columnSpanFull(),
                Section::make('Historial')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->state(fn (RecruitmentApplication $record) => $record->events()->with('user')->latest('id')->get())
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextEntry::make('summary')
                                        ->label('Movimiento')
                                        ->state(fn (RecruitmentApplicationEvent $record): string => self::eventSummary($record)),
                                    TextEntry::make('user.name')->label('Usuario')->placeholder('Sistema'),
                                    TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i a'),
                                ]),
                                TextEntry::make('note')->label('Nota')->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function eventSummary(RecruitmentApplicationEvent $event): string
    {
        if ($event->to_stage === null) {
            return 'Nota';
        }

        if ($event->from_stage === null) {
            return "Ingresó en {$event->to_stage->label()}";
        }

        return "{$event->from_stage->label()} → {$event->to_stage->label()}";
    }
}
