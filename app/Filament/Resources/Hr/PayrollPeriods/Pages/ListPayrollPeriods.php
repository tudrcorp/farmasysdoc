<?php

namespace App\Filament\Resources\Hr\PayrollPeriods\Pages;

use App\Filament\Resources\Hr\PayrollPeriods\PayrollPeriodResource;
use App\Services\Hr\PayrollPeriodGenerator;
use App\Services\Hr\PayrollPeriodVisibility;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
        return 'Solo se muestra el periodo pendiente de calcular. Use los filtros para consultar otro periodo o un estatus distinto.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.resources.hr.payroll-periods.partials.visibility-notice')
                    ->viewData(fn (): array => $this->visibilityNotice())
                    ->columnSpanFull(),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @return array{
     *     graceDays: int,
     *     overdueLabel: ?string,
     *     remainingDays: ?int,
     *     visibleUntil: ?string
     * }
     */
    public function visibilityNotice(): array
    {
        $visibility = app(PayrollPeriodVisibility::class);
        $overdue = $visibility->overduePeriod();
        $remaining = $overdue !== null
            ? $visibility->remainingVisibilityDays($overdue)
            : null;

        return [
            'graceDays' => PayrollPeriodVisibility::GRACE_DAYS,
            'overdueLabel' => $overdue !== null
                ? $overdue->halfLabel().' · '.$overdue->monthLabel()
                : null,
            'remainingDays' => $remaining,
            'visibleUntil' => $overdue?->visibilityEndsOn()->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-hr-payroll-list-page',
            'fi-hr-ios-filters-page',
        ];
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
