<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Filament\Resources\PettyCashFunds\Support\PettyCashListBranchTab;
use App\Filament\Resources\PettyCashFunds\Widgets\ListPettyCashExpensesTable;
use App\Filament\Resources\PettyCashFunds\Widgets\StatsListPettyCashOverview;
use App\Models\Branch;
use App\Services\Finance\PettyCashFundCsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPettyCashFunds extends ListRecords
{
    protected static string $resource = PettyCashFundResource::class;

    protected static ?string $title = 'Caja chica';

    /**
     * @return array<int, string>
     */
    public function getPageClasses(): array
    {
        return array_merge(parent::getPageClasses(), [
            'fi-petty-cash-list-page',
            'fi-fixed-positioning-context',
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Caja chica';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gerencia solicita el fondo, administración lo aprueba con comprobante y luego se registran los gastos hasta agotarlo.';
    }

    public function mount(): void
    {
        parent::mount();

        $this->broadcastActiveBranchTab();
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (PettyCashListBranchTab::branchesForTabs() as $branch) {
            if (! $branch instanceof Branch) {
                continue;
            }

            $branchId = (int) $branch->getKey();
            $tabKey = PettyCashListBranchTab::tabKeyForBranch($branch);

            $tabs[$tabKey] = Tab::make($branch->name)
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where(
                        $query->getModel()->getTable().'.branch_id',
                        $branchId,
                    ),
                )
                ->badge(
                    fn (): string => (string) PettyCashFundResource::getEloquentQuery()
                        ->where('branch_id', $branchId)
                        ->count(),
                );
        }

        return $tabs;
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();

        $this->cachedDefaultTableColumnState = null;

        $this->applyTableColumnManager();

        $this->broadcastActiveBranchTab();
    }

    protected function broadcastActiveBranchTab(): void
    {
        $this->dispatch(
            'petty-cash-list-branch-tab-changed',
            branchId: PettyCashListBranchTab::branchIdFromTabKey($this->activeTab),
        );
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            StatsListPettyCashOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            ListPettyCashExpensesTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Descargar CSV del mes')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->tooltip('Solicitudes de caja chica del mes en curso')
                ->action(function (): StreamedResponse {
                    $user = PettyCashFundResource::currentUser();
                    abort_unless($user !== null, 403);

                    $from = now()->startOfMonth();
                    $to = now()->endOfMonth();
                    $exporter = app(PettyCashFundCsvExporter::class);

                    $query = $exporter->queryForUser($user, $from, $to);
                    $branchId = PettyCashListBranchTab::branchIdFromTabKey($this->activeTab);
                    if ($branchId !== null) {
                        $query->where('branch_id', $branchId);
                    }

                    return $exporter->stream(
                        $query,
                        'caja-chica-'.$from->format('Y-m').($branchId !== null ? '-sucursal-'.$branchId : '').'.csv',
                    );
                })
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ]),
            CreateAction::make()
                ->label('Solicitar caja chica')
                ->icon(Heroicon::Plus)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
