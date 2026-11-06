<?php

namespace App\Filament\Resources\FiscalXReports\Pages;

use App\Enums\FiscalDocumentType;
use App\Enums\FiscalPrinterMode;
use App\Filament\Resources\FiscalXReports\FiscalXReportResource;
use App\Models\FiscalPrinter;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ListFiscalXReports extends ListRecords
{
    protected static string $resource = FiscalXReportResource::class;

    protected static ?string $title = 'Reportes X';

    public function getSubheading(): ?string
    {
        return 'El reporte X es informativo: muestra lo acumulado en la jornada sin cerrarla.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestXReport')
                ->label('Imprimir reporte X')
                ->icon(Heroicon::Printer)
                ->modalHeading('Imprimir reporte X')
                ->modalDescription('Se imprimirá en la máquina elegida. No cierra la jornada fiscal.')
                ->modalSubmitActionLabel('Imprimir X')
                ->schema([
                    Select::make('fiscal_printer_id')
                        ->label('Máquina fiscal')
                        ->options(fn (): array => self::availablePrinters())
                        ->default(fn (): ?int => count(self::availablePrinters()) === 1 ? (int) array_key_first(self::availablePrinters()) : null)
                        ->helperText('Solo máquinas activas (no en simulación ni desactivadas).')
                        ->required()
                        ->native(false),
                ])
                ->disabled(fn (): bool => self::availablePrinters() === [])
                ->tooltip(fn (): ?string => self::availablePrinters() === [] ? 'No hay máquinas fiscales activas en sus sucursales.' : null)
                ->action(function (array $data): void {
                    $printer = BranchAuthScope::apply(FiscalPrinter::query())->find((int) $data['fiscal_printer_id']);

                    if (! $printer instanceof FiscalPrinter || ! self::isPrintable($printer)) {
                        Notification::make()->title('La máquina elegida no está disponible')->danger()->send();

                        return;
                    }

                    $user = Auth::user();

                    app(FiscalDocumentRegistrar::class)->requestReport(
                        $printer,
                        FiscalDocumentType::XReport,
                        (string) ($user?->email ?? $user?->name ?? 'sistema'),
                    );

                    Notification::make()
                        ->title('Reporte X enviado a '.$printer->name)
                        ->body($printer->isOnline()
                            ? 'La máquina fiscal lo imprimirá en unos segundos.'
                            : 'El agente de esta máquina no está en línea; se imprimirá cuando se conecte.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function availablePrinters(): array
    {
        return BranchAuthScope::apply(FiscalPrinter::query())
            ->with('branch:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (FiscalPrinter $printer): bool => self::isPrintable($printer))
            ->mapWithKeys(fn (FiscalPrinter $printer): array => [
                $printer->id => trim(($printer->branch?->name ? $printer->branch->name.' · ' : '').$printer->name.($printer->isOnline() ? '' : ' (sin conexión)')),
            ])
            ->all();
    }

    private static function isPrintable(FiscalPrinter $printer): bool
    {
        return $printer->is_active && $printer->currentMode() === FiscalPrinterMode::Active;
    }
}
