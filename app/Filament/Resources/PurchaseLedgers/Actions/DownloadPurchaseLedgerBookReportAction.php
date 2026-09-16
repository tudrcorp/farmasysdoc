<?php

namespace App\Filament\Resources\PurchaseLedgers\Actions;

use App\Models\PurchaseLedger;
use App\Models\User;
use App\Services\Finance\PurchaseLedgerBookReportBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Livewire\Component as LivewireComponent;

final class DownloadPurchaseLedgerBookReportAction
{
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            self::make('pdf', 'Descargar PDF', Heroicon::DocumentArrowDown),
            self::make('csv', 'Exportar Excel (CSV)', Heroicon::TableCells),
        ])
            ->label('Reporte SENIAT')
            ->icon(Heroicon::BookOpen)
            ->color('gray')
            ->button();
    }

    private static function make(string $format, string $label, Heroicon $icon): Action
    {
        return Action::make('bookReport'.ucfirst($format))
            ->label($label)
            ->icon($icon)
            ->modalHeading('Reporte del Libro de Compras')
            ->modalDescription('Genera el libro SENIAT (facturas y comprobantes de retención) para el periodo y la quincena seleccionados.')
            ->modalSubmitActionLabel($format === 'pdf' ? 'Descargar PDF' : 'Descargar Excel')
            ->modalWidth(Width::Medium)
            ->schema(self::schema())
            ->action(function (array $data, LivewireComponent $livewire) use ($format): void {
                $taxPeriod = (string) ($data['tax_period'] ?? '');
                $half = (string) ($data['half'] ?? PurchaseLedgerBookReportBuilder::HALF_MONTH);

                if ($taxPeriod === '') {
                    Notification::make()
                        ->title('Indique el periodo')
                        ->body('Seleccione el mes del Libro de Compras.')
                        ->danger()
                        ->send();

                    return;
                }

                $url = URL::temporarySignedRoute(
                    'purchase-ledgers.book-report',
                    now()->addMinutes(10),
                    [
                        'tax_period' => $taxPeriod,
                        'half' => $half,
                        'format' => $format,
                    ],
                );

                $livewire->js('window.open('.Js::from($url).', "_blank")');

                Notification::make()
                    ->title('Descarga iniciada')
                    ->body('Se abrió una pestaña con el reporte. Si no aparece, permita ventanas emergentes.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return list<Select>
     */
    private static function schema(): array
    {
        $user = Auth::user();
        $canSee = $user instanceof User;

        return [
            Select::make('tax_period')
                ->label('Periodo')
                ->options(fn (): array => $canSee
                    ? PurchaseLedger::query()
                        ->select('tax_period')
                        ->distinct()
                        ->orderByDesc('tax_period')
                        ->pluck('tax_period', 'tax_period')
                        ->all()
                    : [])
                ->default(now()->format('Y/m'))
                ->required()
                ->searchable()
                ->native(false),
            Select::make('half')
                ->label('Quincena')
                ->options(PurchaseLedgerBookReportBuilder::halfOptions())
                ->default(now()->day <= 15
                    ? PurchaseLedgerBookReportBuilder::HALF_FIRST
                    : PurchaseLedgerBookReportBuilder::HALF_SECOND)
                ->required()
                ->native(false),
        ];
    }
}
