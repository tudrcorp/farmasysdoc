<?php

namespace App\Filament\Resources\PlanServices\Schemas;

use App\Models\PlanService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PlanServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'md' => 3])
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->default(fn (): string => 'SRV-'.str_pad((string) ((int) PlanService::query()->max('id') + 1), 4, '0', STR_PAD_LEFT))
                            ->required()
                            ->maxLength(30)
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Ya existe un servicio con ese código.'])
                            ->prefixIcon(Heroicon::Hashtag),
                        TextInput::make('name')
                            ->label('Nombre del servicio')
                            ->placeholder('Ej.: Consulta médica, Delivery, Medicamentos ambulatorios')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->validationMessages(['unique' => 'Ya existe un servicio con ese nombre.'])
                            ->columnSpan(['md' => 2]),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Los servicios inactivos no se pueden asignar a planes nuevos.')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
