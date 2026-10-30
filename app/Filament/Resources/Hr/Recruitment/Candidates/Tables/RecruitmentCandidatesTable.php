<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates\Tables;

use App\Enums\RecruitmentCandidateSource;
use App\Enums\RecruitmentCvExtractionStatus;
use App\Filament\Resources\Hr\Recruitment\Candidates\Actions\ApplyCandidateToRequestAction;
use App\Models\RecruitmentCandidate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecruitmentCandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('applications'))
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('')
                    ->disk(RecruitmentCandidate::PHOTO_DISK)
                    ->circular(),
                TextColumn::make('last_name')
                    ->label('Candidato')
                    ->state(fn (RecruitmentCandidate $record): string => $record->fullName())
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name'])
                    ->weight('medium'),
                TextColumn::make('national_id')->label('Cédula')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Teléfono')->searchable()->placeholder('—'),
                TextColumn::make('desired_position')->label('Aspira a')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('cv_extraction_status')
                    ->label('CV')
                    ->badge()
                    ->formatStateUsing(fn (RecruitmentCvExtractionStatus $state): string => $state->label())
                    ->color(fn (RecruitmentCvExtractionStatus $state): string => $state->filamentColor()),
                IconColumn::make('reviewed_at')->label('Revisado')->boolean()->state(fn (RecruitmentCandidate $record): bool => $record->isReviewed()),
                TextColumn::make('applications_count')->label('Procesos')->alignCenter(),
                TextColumn::make('employee.job_title')->label('Empleado')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('reviewed_at')->label('Revisado')->nullable(),
                SelectFilter::make('source')->label('Origen')->options(RecruitmentCandidateSource::options()),
            ])
            ->recordActions([
                EditAction::make()->label('Revisar'),
                ApplyCandidateToRequestAction::make(),
                DeleteAction::make(),
            ]);
    }
}
