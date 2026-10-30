<?php

namespace App\Filament\Resources\Hr\Training\Courses\Tables;

use App\Enums\TrainingCourseType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TrainingCoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Curso')->searchable()->sortable()->weight('medium'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (TrainingCourseType $state): string => $state->label()),
                TextColumn::make('duration_hours')->label('Horas')->numeric(1)->alignCenter(),
                IconColumn::make('is_mandatory')->label('Obligatorio')->boolean(),
                IconColumn::make('requires_evaluation')->label('Evaluación')->boolean(),
                TextColumn::make('validity_months')->label('Vigencia')->suffix(' meses')->placeholder('Sin vencimiento'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(TrainingCourseType::options()),
                TernaryFilter::make('is_mandatory')->label('Obligatorio'),
                TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
