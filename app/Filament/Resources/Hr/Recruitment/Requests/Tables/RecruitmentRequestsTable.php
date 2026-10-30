<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Tables;

use App\Enums\RecruitmentRequestPriority;
use App\Enums\RecruitmentRequestStatus;
use App\Filament\Resources\Hr\Recruitment\Requests\Actions\RecruitmentRequestDecisionActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecruitmentRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_title')->label('Cargo')->searchable()->weight('medium'),
                TextColumn::make('branch.name')->label('Sucursal')->badge()->color('info')->sortable(),
                TextColumn::make('vacancies')->label('Vacantes')->alignCenter(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (RecruitmentRequestPriority $state): string => $state->label())
                    ->color(fn (RecruitmentRequestPriority $state): string => $state->filamentColor()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (RecruitmentRequestStatus $state): string => $state->label())
                    ->color(fn (RecruitmentRequestStatus $state): string => $state->filamentColor()),
                TextColumn::make('needed_by')->label('Requerida para')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('requester.name')->label('Solicitada por')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Creada')->date('d/m/Y')->sortable()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(RecruitmentRequestStatus::options()),
                SelectFilter::make('branch_id')->label('Sucursal')->relationship('branch', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                RecruitmentRequestDecisionActions::approve(),
                RecruitmentRequestDecisionActions::reject(),
            ]);
    }
}
