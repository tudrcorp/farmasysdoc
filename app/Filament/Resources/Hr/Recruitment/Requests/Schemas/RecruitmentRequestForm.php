<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Schemas;

use App\Enums\RecruitmentRequestPriority;
use App\Enums\RecruitmentRequestReason;
use App\Filament\Resources\Hr\Recruitment\Requests\RecruitmentRequestResource;
use App\Models\Branch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class RecruitmentRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vacante')
                    ->icon(Heroicon::Briefcase)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->relationship('branch', 'name', fn (Builder $query): Builder => self::allowedBranches($query))
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('job_title')
                                ->label('Cargo')
                                ->required()
                                ->maxLength(160)
                                ->placeholder('Ej.: Cajero, Farmacéutico'),
                            TextInput::make('vacancies')
                                ->label('Cantidad de vacantes')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(100)
                                ->default(1)
                                ->required(),
                            Select::make('reason')
                                ->label('Motivo')
                                ->options(RecruitmentRequestReason::options())
                                ->native(false)
                                ->required(),
                            Select::make('priority')
                                ->label('Prioridad')
                                ->options(RecruitmentRequestPriority::options())
                                ->default(RecruitmentRequestPriority::Normal->value)
                                ->native(false)
                                ->required(),
                            DatePicker::make('needed_by')
                                ->label('Fecha requerida')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                            TextInput::make('reference_salary_usd')
                                ->label('Sueldo referencial (USD)')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->prefix('US$'),
                        ]),
                        Textarea::make('requirements')
                            ->label('Requisitos del cargo')
                            ->rows(4)
                            ->maxLength(3000)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    private static function allowedBranches(Builder $query): Builder
    {
        $query->where('is_active', true)->orderBy('name');

        $user = RecruitmentRequestResource::currentUser();
        if ($user === null || $user->isAdministrator()) {
            return $query;
        }

        return $query->whereKey($user->restrictedBranchIdsForQueries());
    }
}
