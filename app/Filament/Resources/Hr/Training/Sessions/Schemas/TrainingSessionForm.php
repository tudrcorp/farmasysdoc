<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Schemas;

use App\Enums\TrainingModality;
use App\Models\Employee;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class TrainingSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Capacitación')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('training_course_id')
                                ->label('Curso')
                                ->relationship('course', 'name', fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->helperText('Déjala vacía si es general para toda la empresa.')
                                ->relationship('branch', 'name', fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'))
                                ->searchable()
                                ->preload(),
                            DateTimePicker::make('starts_at')
                                ->label('Inicio')
                                ->native(false)
                                ->seconds(false)
                                ->displayFormat('d/m/Y h:i a')
                                ->live(onBlur: true)
                                ->required(),
                            DateTimePicker::make('ends_at')
                                ->label('Fin')
                                ->native(false)
                                ->seconds(false)
                                ->displayFormat('d/m/Y h:i a')
                                ->after('starts_at')
                                ->validationMessages(['after' => 'El fin debe ser posterior al inicio.'])
                                ->required(),
                            Select::make('modality')
                                ->label('Modalidad')
                                ->options(TrainingModality::options())
                                ->native(false)
                                ->live()
                                ->required(),
                            TextInput::make('location')
                                ->label('Lugar')
                                ->maxLength(255)
                                ->visible(fn (Get $get): bool => $get('modality') !== TrainingModality::Online->value),
                            TextInput::make('meeting_url')
                                ->label('Enlace')
                                ->url()
                                ->maxLength(255)
                                ->visible(fn (Get $get): bool => $get('modality') === TrainingModality::Online->value),
                            Select::make('facilitator_employee_id')
                                ->label('Facilitador (empleado)')
                                ->options(fn (): array => Employee::query()
                                    ->where('is_active', true)
                                    ->orderBy('last_name')
                                    ->get()
                                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->fullName()])
                                    ->all())
                                ->searchable(),
                            TextInput::make('facilitator_name')
                                ->label('Facilitador externo')
                                ->helperText('Úsalo si no es empleado de la empresa.')
                                ->maxLength(180),
                            TextInput::make('capacity')
                                ->label('Cupos')
                                ->helperText('Vacío si no hay límite.')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(1000),
                        ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Costo y notas')
                    ->icon(Heroicon::Banknotes)
                    ->description('El costo es solo un registro: no afecta la nómina.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('cost_usd')->label('Costo (USD)')->numeric()->minValue(0)->step(0.01)->prefix('US$'),
                            TextInput::make('cost_ves')->label('Costo (VES)')->numeric()->minValue(0)->step(0.01)->prefix('Bs'),
                        ]),
                        Textarea::make('notes')->label('Notas')->rows(3)->maxLength(2000)->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
