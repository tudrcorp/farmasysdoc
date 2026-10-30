<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Tables;

use App\Enums\TrainingSessionStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrainingSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.name')->label('Curso')->searchable()->weight('medium'),
                TextColumn::make('starts_at')->label('Inicio')->dateTime('d/m/Y h:i a')->sortable(),
                TextColumn::make('branch.name')->label('Sucursal')->placeholder('General')->badge()->color('info'),
                TextColumn::make('enrollments_count')->label('Inscritos')->counts('enrollments')->alignCenter(),
                TextColumn::make('capacity')->label('Cupos')->placeholder('Sin límite')->alignCenter(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (TrainingSessionStatus $state): string => $state->label())
                    ->color(fn (TrainingSessionStatus $state): string => $state->filamentColor()),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(TrainingSessionStatus::options()),
                SelectFilter::make('training_course_id')->label('Curso')->relationship('course', 'name')->searchable()->preload(),
                SelectFilter::make('branch_id')->label('Sucursal')->relationship('branch', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
