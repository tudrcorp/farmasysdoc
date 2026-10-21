<?php

namespace App\Filament\Resources\PettyCashFunds\Widgets;

use App\Enums\PettyCashCurrency;
use App\Filament\Resources\PettyCashFunds\Concerns\SyncsWithPettyCashListBranchTab;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Models\PettyCashExpense;
use App\Services\Finance\PettyCashExpenseCsvExporter;
use App\Support\Finance\PettyCashMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPettyCashExpensesTable extends TableWidget
{
    use SyncsWithPettyCashListBranchTab;

    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.petty-cash-funds.widgets.expenses-table-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Gastos registrados';

    protected static ?string $description = 'Egresos de la sucursal seleccionada arriba. Filtre por rango de fechas.';

    public function mount(): void
    {
        $this->mountSyncsWithPettyCashListBranchTab();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseExpenseQuery())
            ->columns([
                TextColumn::make('spent_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('petty_cash_fund_id')
                    ->label('Solicitud')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? '#'.$state : '—')
                    ->url(fn (PettyCashExpense $record): ?string => $record->petty_cash_fund_id !== null
                        ? PettyCashFundResource::getUrl('view', ['record' => $record->petty_cash_fund_id], isAbsolute: false)
                        : null)
                    ->color('primary'),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->formatStateUsing(fn (PettyCashCurrency|string|null $state): string => $state instanceof PettyCashCurrency
                        ? $state->shortLabel()
                        : (PettyCashCurrency::tryFrom((string) $state)?->shortLabel() ?? '—')),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->state(fn (PettyCashExpense $record): string => ($record->currency instanceof PettyCashCurrency
                        ? $record->currency->prefix().' '
                        : '')
                        .number_format((float) $record->amount, 2, ',', '.'))
                    ->description(fn (PettyCashExpense $record): string => PettyCashMoney::formatUsd((float) $record->amount_usd)
                        .' · '
                        .PettyCashMoney::formatVes((float) $record->amount_ves))
                    ->alignEnd(),
                TextColumn::make('registeredBy.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('spent_at', 'desc')
            ->filters([
                Filter::make('spent_between')
                    ->label('Fecha del gasto')
                    ->form([
                        DatePicker::make('spent_from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('spent_until')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['spent_from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('spent_at', '>=', (string) $data['spent_from']),
                            )
                            ->when(
                                filled($data['spent_until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('spent_at', '<=', (string) $data['spent_until']),
                            );
                    }),
            ])
            ->filtersFormColumns(2)
            ->headerActions([
                Action::make('exportExpensesCsv')
                    ->label('Descargar CSV')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->action(function (): StreamedResponse {
                        $user = PettyCashFundResource::currentUser();
                        abort_unless($user !== null, 403);

                        $exporter = app(PettyCashExpenseCsvExporter::class);

                        return $exporter->stream(
                            $this->getFilteredTableQuery(),
                            'egresos-caja-chica-'.now()->format('Y-m-d-His').'.csv',
                        );
                    })
                    ->extraAttributes([
                        'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin gastos registrados')
            ->emptyStateDescription('Cuando gerencia registre facturas o tickets en una caja chica aprobada, aparecerán aquí.');
    }

    /**
     * @return Builder<PettyCashExpense>
     */
    private function baseExpenseQuery(): Builder
    {
        $fundQuery = $this->applyPettyCashListBranchScope(PettyCashFundResource::getEloquentQuery());
        $fundIds = (clone $fundQuery)->select('id');

        return PettyCashExpense::query()
            ->whereIn('petty_cash_fund_id', $fundIds)
            ->with(['fund.branch', 'registeredBy']);
    }
}
