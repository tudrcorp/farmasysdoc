<?php

namespace App\Filament\Resources\FiscalXReports\Tables;

use App\Enums\FiscalDocumentStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Support\Filament\BranchAuthScope;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FiscalXReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),
                TextColumn::make('fiscalPrinter.branch.name')
                    ->label('Sucursal')
                    ->description(fn (FiscalDocument $record): ?string => $record->fiscalPrinter?->name),
                TextColumn::make('requested_by')
                    ->label('Solicitado por')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (FiscalDocumentStatus $state): string => $state->label())
                    ->badge()
                    ->color(fn (FiscalDocumentStatus $state): string => $state->color()),
                TextColumn::make('printed_at')
                    ->label('Impreso')
                    ->dateTime('d/m/Y h:i A')
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->placeholder('—')
                    ->color('danger')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('15s')
            ->filters([
                SelectFilter::make('fiscal_printer_id')
                    ->label('Máquina fiscal')
                    ->options(fn (): array => BranchAuthScope::apply(FiscalPrinter::query())->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(FiscalDocumentStatus::options()),
            ])
            ->emptyStateHeading('Sin reportes X')
            ->emptyStateDescription('Use «Imprimir reporte X» para pedir uno a una máquina fiscal.')
            ->emptyStateIcon(Heroicon::DocumentText);
    }
}
