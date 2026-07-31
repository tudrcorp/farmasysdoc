<?php

namespace App\Filament\Resources\Hr\PayrollPeriods\Pages;

use App\Filament\Resources\Hr\PayrollPeriods\PayrollPeriodResource;
use App\Services\Hr\PayrollPeriodGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Nómina';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Nómina';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return '24 periodos al año · pago el día 15 y al cierre de cada mes. Ordenados del periodo 1 al 24.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generatePeriods')
                ->label('Generar periodos del año')
                ->icon(Heroicon::CalendarDays)
                ->color('primary')
                ->form([
                    TextInput::make('year')
                        ->label('Año')
                        ->numeric()
                        ->integer()
                        ->required()
                        ->default((int) now()->year)
                        ->minValue(2020)
                        ->maxValue(2100)
                        ->helperText('Crea o asegura los 24 periodos (1.ª y 2.ª quincena de cada mes).'),
                ])
                ->modalHeading('Generar periodos de nómina')
                ->modalSubmitActionLabel('Generar')
                ->action(function (array $data): void {
                    try {
                        $year = (int) $data['year'];
                        $periods = app(PayrollPeriodGenerator::class)->generateForYear($year);
                        Notification::make()
                            ->title('Periodos listos')
                            ->body("Se aseguraron {$periods->count()} periodos para el año {$year}, ordenados del 1 al 24.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('No se pudieron generar los periodos')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
