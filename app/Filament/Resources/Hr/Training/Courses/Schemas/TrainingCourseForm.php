<?php

namespace App\Filament\Resources\Hr\Training\Courses\Schemas;

use App\Enums\TrainingCourseType;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TrainingCourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Curso')
                    ->icon(Heroicon::BookOpen)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->label('Nombre')->required()->maxLength(180),
                            Select::make('type')
                                ->label('Tipo')
                                ->options(TrainingCourseType::options())
                                ->native(false)
                                ->required()
                                ->helperText('Las inducciones se asignan solas al contratar desde Reclutamiento.'),
                            TextInput::make('duration_hours')
                                ->label('Duración (horas)')
                                ->numeric()
                                ->minValue(0.5)
                                ->step(0.5)
                                ->required(),
                            Toggle::make('is_active')->label('Activo')->default(true)->inline(false),
                        ]),
                        Textarea::make('description')->label('Descripción')->rows(3)->maxLength(2000)->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Reglas')
                    ->icon(Heroicon::ShieldCheck)
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_mandatory')->label('Obligatorio')->live()->inline(false),
                            Toggle::make('requires_evaluation')
                                ->label('Requiere evaluación')
                                ->helperText('El certificado solo sale si el empleado asistió y aprobó.')
                                ->inline(false),
                            TagsInput::make('required_job_titles')
                                ->label('Cargos que deben hacerlo')
                                ->helperText('Déjalo vacío para que aplique a todo el personal.')
                                ->suggestions(fn (): array => Employee::query()->whereNotNull('job_title')->distinct()->orderBy('job_title')->pluck('job_title')->all())
                                ->visible(fn (Get $get): bool => (bool) $get('is_mandatory')),
                            TextInput::make('validity_months')
                                ->label('Vigencia (meses)')
                                ->helperText('Déjalo vacío si no se vence.')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(120),
                        ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
