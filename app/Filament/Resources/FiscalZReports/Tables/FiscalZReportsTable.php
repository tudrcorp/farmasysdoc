<?php

namespace App\Filament\Resources\FiscalZReports\Tables;

use App\Enums\FiscalZReportSource;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Support\Filament\BranchAuthScope;
use Carbon\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class FiscalZReportsTable
{
    private const string SALES_TOTAL_SQL = 'exempt_sales_ves + general_base_sales_ves + general_tax_sales_ves + reduced_base_sales_ves + reduced_tax_sales_ves + additional_base_sales_ves + additional_tax_sales_ves';

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report_datetime')
                    ->label('Fecha')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->description(fn (FiscalZReport $record): ?string => $record->fiscalPrinter?->name)
                    ->sortable(),
                TextColumn::make('z_number')
                    ->label('Z Nº')
                    ->formatStateUsing(fn (int $state): string => str_pad((string) $state, 4, '0', STR_PAD_LEFT))
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('last_invoice_number')
                    ->label('Última factura')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? str_pad((string) $state, 8, '0', STR_PAD_LEFT) : '—'),
                TextColumn::make('exempt_sales_ves')
                    ->label('Exento')
                    ->numeric(2)
                    ->alignEnd()
                    ->summarize(Sum::make()->label('')->numeric(2)),
                TextColumn::make('general_base_sales_ves')
                    ->label('Base 16%')
                    ->numeric(2)
                    ->alignEnd()
                    ->summarize(Sum::make()->label('')->numeric(2)),
                TextColumn::make('general_tax_sales_ves')
                    ->label('IVA 16%')
                    ->numeric(2)
                    ->alignEnd()
                    ->summarize(Sum::make()->label('')->numeric(2)),
                TextColumn::make('igtf_tax_sales_ves')
                    ->label('IGTF')
                    ->numeric(2)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('')->numeric(2)),
                TextColumn::make('sales_total')
                    ->label('Total ventas (Bs)')
                    ->state(fn (FiscalZReport $record): float => $record->salesTotalVes())
                    ->numeric(2)
                    ->alignEnd()
                    ->weight('semibold')
                    ->summarize(Summarizer::make()
                        ->label('Total')
                        ->using(fn (QueryBuilder $query): float => round((float) $query->sum(DB::raw(self::SALES_TOTAL_SQL)), 2))
                        ->numeric(2)),
                TextColumn::make('last_credit_note_number')
                    ->label('Última NC')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (FiscalZReportSource $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('integrity')
                    ->label('Íntegro')
                    ->state(fn (FiscalZReport $record): bool => $record->hasValidContentHash())
                    ->boolean()
                    ->tooltip(fn (FiscalZReport $record): string => $record->hasValidContentHash()
                        ? 'Los montos no se han modificado desde que se leyeron de la máquina.'
                        : 'La huella no coincide: los datos cambiaron después de la captura.')
                    ->alignCenter(),
            ])
            ->defaultSort('report_datetime', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => BranchAuthScope::branchOptions()),
                SelectFilter::make('fiscal_printer_id')
                    ->label('Máquina fiscal')
                    ->options(fn (): array => BranchAuthScope::apply(FiscalPrinter::query())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Filter::make('report_date')
                    ->schema([
                        DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('report_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('report_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'Desde '.Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if (filled($data['until'] ?? null)) {
                            $indicators[] = 'Hasta '.Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ])
            ->emptyStateHeading('Sin reportes Z')
            ->emptyStateDescription('Los reportes Z se capturan al imprimirlos o leerlos desde la máquina fiscal.')
            ->emptyStateIcon(Heroicon::LockClosed);
    }
}
