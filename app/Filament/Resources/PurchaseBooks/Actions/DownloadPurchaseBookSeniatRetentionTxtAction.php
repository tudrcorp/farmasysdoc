<?php

namespace App\Filament\Resources\PurchaseBooks\Actions;

use App\Models\PurchaseBook;
use App\Models\User;
use App\Services\Finance\PurchaseLedgerBookReportBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Livewire\Component as LivewireComponent;

final class DownloadPurchaseBookSeniatRetentionTxtAction
{
    public static function make(): Action
    {
        return Action::make('seniatRetentionTxt')
            ->label('TXT SENIAT')
            ->icon(Heroicon::ArrowDownTray)
            ->color('gray')
            ->modalHeading('Archivo TXT de retención de IVA')
            ->modalDescription('Genera el archivo delimitado por tabulaciones para cargarlo en el portal del SENIAT. Una fila por factura.')
            ->modalSubmitActionLabel('Descargar TXT')
            ->modalWidth(Width::Medium)
            ->schema(self::schema())
            ->action(function (array $data, LivewireComponent $livewire): void {
                $taxPeriod = (string) ($data['tax_period'] ?? '');
                $half = (string) ($data['half'] ?? PurchaseLedgerBookReportBuilder::HALF_MONTH);

                if ($taxPeriod === '') {
                    Notification::make()
                        ->title('Indique el periodo')
                        ->body('Seleccione el mes de las retenciones.')
                        ->danger()
                        ->send();

                    return;
                }

                $url = URL::temporarySignedRoute(
                    'purchase-books.seniat-retention-txt',
                    now()->addMinutes(10),
                    [
                        'tax_period' => $taxPeriod,
                        'half' => $half,
                    ],
                );

                $livewire->js('window.open('.Js::from($url).', "_blank")');

                Notification::make()
                    ->title('Descarga iniciada')
                    ->body('Se abrió una pestaña con el archivo TXT. Si no aparece, permita ventanas emergentes.')
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
                    ? PurchaseBook::query()
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
