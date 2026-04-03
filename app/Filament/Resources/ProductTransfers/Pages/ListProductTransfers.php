<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ListProductTransfers extends ListRecords
{
    protected static string $resource = ProductTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('transferReportPdf')
                ->label('Reporte PDF (rango)')
                ->icon(Heroicon::DocumentArrowDown)
                ->color('gray')
                ->modalHeading('Reporte de traslados')
                ->modalDescription('PDF con los traslados registrados entre dos fechas (por fecha de registro del documento).')
                ->modalSubmitActionLabel('Descargar PDF')
                ->modalWidth(Width::Medium)
                ->schema([
                    DatePicker::make('date_from')
                        ->label('Desde')
                        ->default(now()->startOfMonth())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('date_until')
                        ->label('Hasta')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                ])
                ->action(function (array $data): void {
                    $from = Carbon::parse((string) $data['date_from'])->startOfDay();
                    $until = Carbon::parse((string) $data['date_until'])->endOfDay();

                    if ($until->lt($from)) {
                        Notification::make()
                            ->title('Rango de fechas inválido')
                            ->body('«Hasta» no puede ser anterior a «Desde».')
                            ->danger()
                            ->send();

                        return;
                    }

                    $url = URL::temporarySignedRoute(
                        'product-transfers.report-pdf',
                        now()->addMinutes(10),
                        [
                            'from' => $from->toDateString(),
                            'until' => $until->toDateString(),
                        ]
                    );

                    $this->js('window.open('.Js::from($url).', "_blank")');

                    Notification::make()
                        ->title('Descarga iniciada')
                        ->body('Se abrió una pestaña con el PDF. Si no aparece, permita ventanas emergentes.')
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->icon(Heroicon::Plus)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
